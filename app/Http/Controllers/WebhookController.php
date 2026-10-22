<?php

namespace App\Http\Controllers;

use App\Jobs\ApplyStockNotification;
use App\Models\Dealer;
use App\Models\Integration;
use App\Models\WebhookEvent;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = (string) config('autotrader.webhook_secret');
        $raw = $request->getContent();
        $given = trim((string) ($request->header('X-Autotrader-Signature')
            ?? $request->header('Autotrader-Signature')
            ?? $request->header('X-Signature')
            ?? ''));
        $given = (string) preg_replace('/^sha256=/i', '', $given);
        $expected = config('autotrader.webhook_hash') === 'prefix-sha256'
            ? hash('sha256', $secret.$raw)
            : hash_hmac('sha256', $raw, $secret);

        if ($secret === '' || $given === '' || ! hash_equals($expected, strtolower($given))) {
            return response('Invalid signature', 401);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            $payload = ['raw' => $raw];
        }

        $advertiserId = (string) ($payload['advertiserId'] ?? $payload['advertiser']['advertiserId'] ?? '');
        $dealer = $advertiserId !== '' ? Dealer::query()->where('advertiser_id', $advertiserId)->first() : null;
        $environment = (string) config('autotrader.environment');
        $integration = $dealer?->integration
            ?? Integration::query()->where('environment', $environment)->first();

        if (! $integration) {
            return response('Integration is not configured', 500);
        }

        $type = strtoupper((string) ($payload['type'] ?? $payload['eventType'] ?? ($payload['stockId'] ?? null ? 'STOCK_UPDATE' : 'ADVERTISER')));
        $stockId = $payload['stockId'] ?? $payload['metadata']['stockId'] ?? null;
        $time = $payload['notificationTime'] ?? $payload['time'] ?? $payload['timestamp'] ?? null;
        $notificationTime = null;
        if ($time) {
            try {
                $notificationTime = Carbon::parse($time)->utc();
            } catch (\Throwable) {
                $notificationTime = null;
            }
        }

        try {
            $event = WebhookEvent::query()->create([
                'integration_id' => $integration->id,
                'dealer_id' => $dealer?->id,
                'advertiser_id' => $advertiserId !== '' ? $advertiserId : null,
                'stock_id' => $stockId ? (string) $stockId : null,
                'event_type' => mb_substr($type, 0, 40),
                'notification_time' => $notificationTime,
                'source_integration_id' => $payload['integrationId'] ?? null,
                'change_fields' => $payload['changeFields'] ?? $payload['change_fields'] ?? null,
                'payload_hash' => hash('sha256', $raw),
                'raw_body' => $payload,
                'signature_valid' => true,
                'status' => 'received',
                'received_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return response('Accepted', 200);
        }

        ApplyStockNotification::dispatch($event->id);

        return response('Accepted', 200);
    }
}
