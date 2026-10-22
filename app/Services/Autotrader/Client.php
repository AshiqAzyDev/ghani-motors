<?php

namespace App\Services\Autotrader;

use App\Exceptions\AutotraderException;
use App\Models\ApiAccessToken;
use App\Models\ApiBlock;
use App\Models\ApiCallLog;
use App\Models\Integration;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class Client
{
    public function __construct(private Integration $integration) {}

    public static function forIntegration(Integration $integration): self
    {
        return new self($integration);
    }

    public function get(string $service, string $path, array $query = [], ?string $advertiserId = null): array
    {
        return $this->send('GET', $service, $path, $query, null, $advertiserId);
    }

    private function send(string $method, string $service, string $path, array $query, ?array $json, ?string $advertiserId): array
    {
        $this->assertNotBlocked($service, $advertiserId);

        $token = $service === 'auth' ? null : $this->token();
        $url = config('autotrader.base_url').$path;

        $pending = Http::timeout((int) config('autotrader.timeout'))
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'GhaniMotors/1.0']);

        if ($token) {
            $pending = $pending->withToken($token);
        }

        $response = $method === 'GET'
            ? $pending->get($url, $query)
            : $pending->post($url, $json ?? []);

        return $this->handle($service, $path, $advertiserId, $response);
    }

    public function token(): string
    {
        $current = ApiAccessToken::query()
            ->where('integration_id', $this->integration->id)
            ->whereNull('invalidated_at')
            ->where('expires_at', '>', now()->addMinute())
            ->latest('id')
            ->first();

        if ($current) {
            return $current->access_token;
        }

        $key = (string) config('autotrader.key');
        $secret = (string) config('autotrader.secret');
        if ($key === '' || $secret === '') {
            throw new AutotraderException('Autotrader key and secret are not set in the environment.', needsAction: true);
        }

        $payload = $this->send('POST', 'auth', config('autotrader.paths.authenticate'), [], [
            'key' => $key,
            'secret' => $secret,
        ], null);

        $access = $payload['access_token'] ?? $payload['accessToken'] ?? null;
        if (! is_string($access) || $access === '') {
            throw new AutotraderException('Autotrader did not return an access token.', needsAction: true);
        }

        $seconds = (int) ($payload['expires_in'] ?? $payload['expiresIn'] ?? 900);
        ApiAccessToken::query()->create([
            'integration_id' => $this->integration->id,
            'access_token' => $access,
            'obtained_at' => now(),
            'expires_at' => now()->addSeconds(max(60, $seconds)),
        ]);

        ApiBlock::query()
            ->where('integration_id', $this->integration->id)
            ->whereNull('resolved_at')
            ->where('scope', 'global')
            ->where('reason', 'like', '401%')
            ->update(['resolved_at' => now()]);

        return $access;
    }

    private function handle(string $service, string $path, ?string $advertiserId, Response $response): array
    {
        $status = $response->status();
        $ray = $response->header('CF-RAY');
        $retryAfter = (int) $response->header('Retry-After');

        if ($response->successful()) {
            $this->integration->credentials_last_used_at = now();
            $this->integration->save();
            $this->log($service, $advertiserId, $status, $ray, $path, null, false, false, null);

            return $response->json() ?? [];
        }

        $body = mb_substr($response->body(), 0, 2000);
        $retryable = false;
        $needsAction = false;

        if ($status === 429) {
            $retryable = true;
            $pause = max(1, $retryAfter);
            $this->block('global', null, null, now()->addSeconds($pause), '429 pausing every service', $ray);
        } elseif ($status === 503) {
            $retryable = true;
            $pause = max(2, $retryAfter);
            $this->block('service', $service, null, now()->addSeconds($pause), '503 pausing '.$service, $ray);
        } elseif ($status === 400) {
            $needsAction = true;
        } elseif ($status === 401) {
            $needsAction = true;
            if ($service !== 'auth') {
                ApiAccessToken::query()
                    ->where('integration_id', $this->integration->id)
                    ->whereNull('invalidated_at')
                    ->update(['invalidated_at' => now()]);
                $this->block('global', null, null, null, '401 until a new token is issued', $ray);
            }
        } elseif ($status === 403) {
            $needsAction = true;
            $lower = strtolower($body);
            $endpointDenied = str_contains($lower, 'product') || str_contains($lower, 'not allowed to access endpoint');
            $this->block(
                'advertiser_service',
                $endpointDenied ? $service : null,
                $advertiserId,
                null,
                $endpointDenied ? '403 this key cannot access '.$service : '403 advertiser is not on this integration',
                $ray,
            );
        }

        $this->log($service, $advertiserId, $status, $ray ?: null, $path, $body, $retryable, $needsAction, $retryAfter ?: null);

        throw new AutotraderException(
            'Autotrader '.$service.' returned HTTP '.$status.'.',
            $retryable,
            $needsAction,
            $status,
        );
    }

    private function assertNotBlocked(string $service, ?string $advertiserId): void
    {
        $blocked = ApiBlock::query()
            ->where('integration_id', $this->integration->id)
            ->whereNull('resolved_at')
            ->where(function ($query) {
                $query->whereNull('pause_until')->orWhere('pause_until', '>', now());
            })
            ->where(function ($query) use ($service, $advertiserId) {
                $query->where(function ($query) use ($service) {
                    if ($service === 'auth') {
                        $query->whereRaw('0 = 1');
                    } else {
                        $query->where('scope', 'global');
                    }
                })->orWhere(function ($query) use ($service) {
                    $query->where('scope', 'service')->where('service', $service);
                });
                if ($advertiserId) {
                    $query->orWhere(function ($query) use ($service, $advertiserId) {
                        $query->where('scope', 'advertiser_service')
                            ->where('advertiser_id', $advertiserId)
                            ->where(function ($query) use ($service) {
                                $query->whereNull('service')->orWhere('service', $service);
                            });
                    });
                }
            })
            ->exists();

        if ($blocked) {
            throw new AutotraderException('Autotrader calls are paused for '.$service.'.', retryable: true);
        }
    }

    private function block(string $scope, ?string $service, ?string $advertiserId, $until, string $reason, ?string $ray): void
    {
        ApiBlock::query()->create([
            'integration_id' => $this->integration->id,
            'scope' => $scope,
            'service' => $service,
            'advertiser_id' => $advertiserId,
            'pause_until' => $until,
            'reason' => $reason,
            'cf_ray_id' => $ray ?: null,
        ]);
    }

    private function log(string $service, ?string $advertiserId, ?int $status, ?string $ray, string $path, ?string $error, bool $retryable, bool $needsAction, ?int $retryAfter): void
    {
        if ($status !== null && $status < 400) {
            return;
        }

        ApiCallLog::query()->create([
            'integration_id' => $this->integration->id,
            'service' => $service,
            'advertiser_id' => $advertiserId,
            'http_status' => $status,
            'cf_ray_id' => $ray,
            'path' => mb_substr($path, 0, 500),
            'error_body' => $error,
            'retryable' => $retryable,
            'needs_action' => $needsAction,
            'retry_after_seconds' => $retryAfter,
        ]);
    }
}
