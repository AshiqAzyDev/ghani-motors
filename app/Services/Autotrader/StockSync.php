<?php

namespace App\Services\Autotrader;

use App\Exceptions\AutotraderException;
use App\Models\Dealer;
use App\Models\Integration;
use App\Models\SyncRun;
use App\Models\Vehicle;
use App\Models\VehicleChargeTime;
use App\Models\VehicleChargeTimeFetch;
use App\Models\VehicleFeature;
use App\Models\VehicleHighlight;
use App\Models\VehicleImage;
use App\Models\VehiclePayload;
use App\Models\VehiclePriceHistory;
use App\Models\VehicleTaxonomyFetch;
use App\Models\WebhookEvent;
use App\Services\AppNotifier;
use App\Services\Mail\GhaniMailer;
use App\Services\VehicleStatus;
use Illuminate\Support\Str;

class StockSync
{
    public function __construct(
        private VehicleStatus $status,
        private GhaniMailer $mailer,
        private AppNotifier $notifier,
    ) {}

    public function baseline(Dealer $dealer, ?int $staffId = null): SyncRun
    {
        $integration = $dealer->integration;
        $this->guardEnvironment($integration);
        if (! $dealer->advertiser_id) {
            throw new AutotraderException('This dealer has no advertiser id.', needsAction: true);
        }

        $run = SyncRun::query()->create([
            'integration_id' => $integration->id,
            'dealer_id' => $dealer->id,
            'environment' => $integration->environment,
            'kind' => 'baseline',
            'status' => 'running',
            'started_by' => $staffId,
            'started_at' => now(),
        ]);

        try {
            $client = Client::forIntegration($integration);
            $page = 1;
            $seen = [];
            $total = null;
            $pageSize = max(1, (int) config('autotrader.page_size'));
            $lastCount = 0;

            do {
                $payload = $client->get('stock', config('autotrader.paths.stock'), [
                    'advertiserId' => $dealer->advertiser_id,
                    'page' => $page,
                    'pageSize' => $pageSize,
                ], $dealer->advertiser_id);

                $total = $payload['totalResults'] ?? $total;
                $items = $payload['results'] ?? $payload['stock'] ?? [];
                if (isset($items['metadata'])) {
                    $items = [$items];
                }
                $lastCount = is_array($items) ? count($items) : 0;

                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $vehicle = $this->upsert($dealer, $this->unwrap($item), [
                        'sync_run_id' => $run->id,
                        'source' => 'sync',
                        'sections' => null,
                    ], $client);
                    if ($vehicle?->at_stock_id) {
                        $seen[] = $vehicle->at_stock_id;
                    }
                }

                $run->pages_done = $page;
                $run->page_cursor = (string) $page;
                $run->total_results = $total;
                $run->vehicles_upserted = count($seen);
                $run->save();
                $page++;
            } while ($lastCount === $pageSize && ($total === null || count($seen) < (int) $total));

            $complete = ($total !== null && count($seen) >= (int) $total)
                || ($total === null && $lastCount < $pageSize);
            if (! $complete) {
                throw new AutotraderException('Baseline stopped before every stock record was read. Cars were not hidden.');
            }

            $this->hideMissing($dealer, $integration->environment, $seen);
            $run->status = 'succeeded';
            $run->finished_at = now();
            $run->save();

            if ($integration->notifications_paused) {
                $integration->notifications_paused = false;
                $integration->pause_reason = null;
                $integration->save();
            }
        } catch (\Throwable $exception) {
            $run->status = 'failed';
            $run->error = $exception->getMessage();
            $run->finished_at = now();
            $run->save();
            $this->mailer->syncFailure(
                $dealer,
                'Autotrader stock sync failed',
                'Baseline sync failed: '.$exception->getMessage()
            );
            $this->notifier->notifyStaff(
                $dealer,
                'sync.failure',
                'Autotrader stock sync failed',
                $exception->getMessage(),
                route('dealer.settings'),
                ['admin']
            );
            throw $exception;
        }

        return $run;
    }

    public function applyNotification(WebhookEvent $event): void
    {
        if ($event->status !== 'received') {
            return;
        }

        $integration = Integration::query()->findOrFail($event->integration_id);
        $finish = function (string $status, ?string $reason = null, ?int $vehicleId = null) use ($event) {
            $event->status = $status;
            $event->ignore_reason = $reason;
            $event->vehicle_id = $vehicleId ?? $event->vehicle_id;
            $event->processed_at = now();
            $event->save();
        };

        if ($integration->notifications_paused) {
            $finish('ignored', 'Notifications are paused until the next baseline.');

            return;
        }

        if (strtoupper($event->event_type) === 'ADVERTISER') {
            $finish('ignored', 'Advertiser membership is refreshed by the Advertisers API.');

            return;
        }

        if ($event->source_integration_id && $integration->at_integration_id
            && $event->source_integration_id === $integration->at_integration_id) {
            $finish('ignored', 'Ignored because this integration sent the update.');

            return;
        }

        $sections = $this->sectionsFromChange($event->change_fields);
        if ($sections === []) {
            $finish('ignored', 'change_fields did not include anything this site stores.');

            return;
        }

        $dealer = Dealer::query()->find($event->dealer_id);
        if (! $dealer || ! $event->stock_id) {
            $finish('failed', 'The notification had no matching dealer or stock id.');

            return;
        }

        $existing = Vehicle::query()->where('at_stock_id', $event->stock_id)->first();
        if ($existing && $event->notification_time && $existing->last_notification_at
            && $event->notification_time->lt($existing->last_notification_at)) {
            $finish('ignored', 'A newer notification is already applied.', $existing->id);

            return;
        }

        try {
            $client = Client::forIntegration($integration);
            $item = $this->unwrap($client->get('stock', config('autotrader.paths.stock').'/'.$event->stock_id, [
                'advertiserId' => $dealer->advertiser_id,
            ], $dealer->advertiser_id));
            $vehicle = $this->upsert($dealer, $item, [
                'webhook_event_id' => $event->id,
                'source' => 'webhook',
                'sections' => $sections,
                'notification_time' => $event->notification_time,
            ], $client);
            $finish('applied', null, $vehicle?->id);
        } catch (\Throwable $exception) {
            $finish('failed', mb_substr($exception->getMessage(), 0, 255), $existing?->id);
            $this->mailer->syncFailure(
                $dealer,
                'Autotrader webhook update failed',
                'Stock '.$event->stock_id.' could not be updated: '.$exception->getMessage()
            );
            $this->notifier->notifyStaff(
                $dealer,
                'sync.failure',
                'Autotrader webhook update failed',
                'Stock '.$event->stock_id.': '.$exception->getMessage(),
                route('dealer.settings'),
                ['admin']
            );
        }
    }

    public function refreshAdvertisers(Integration $integration): SyncRun
    {
        $dealer = $integration->dealers()->orderBy('id')->firstOrFail();
        $run = SyncRun::query()->create([
            'integration_id' => $integration->id,
            'dealer_id' => $dealer->id,
            'environment' => $integration->environment,
            'kind' => 'advertisers',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $client = Client::forIntegration($integration);
            $page = 1;
            $ids = [];
            $pageSize = max(1, (int) config('autotrader.page_size'));
            do {
                $payload = $client->get('advertisers', config('autotrader.paths.advertisers'), [
                    'page' => $page,
                    'pageSize' => $pageSize,
                ]);
                $items = $payload['results'] ?? $payload['advertisers'] ?? [];
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $id = $item['advertiserId'] ?? $item['id'] ?? null;
                    if ($id) {
                        $ids[] = (string) $id;
                    }
                }
                $count = is_array($items) ? count($items) : 0;
                $page++;
            } while ($count === $pageSize && $page < 100);

            foreach ($integration->dealers as $row) {
                if ($row->advertiser_id) {
                    $row->on_integration = in_array((string) $row->advertiser_id, $ids, true);
                    $row->save();
                }
            }

            $run->status = 'succeeded';
            $run->vehicles_upserted = count($ids);
            $run->finished_at = now();
            $run->save();
        } catch (\Throwable $exception) {
            $run->status = 'failed';
            $run->error = $exception->getMessage();
            $run->finished_at = now();
            $run->save();
            $this->mailer->syncFailure(
                $dealer,
                'Autotrader advertisers refresh failed',
                'Advertisers API refresh failed: '.$exception->getMessage()
            );
            $this->notifier->notifyStaff(
                $dealer,
                'sync.failure',
                'Autotrader advertisers refresh failed',
                $exception->getMessage(),
                route('dealer.settings'),
                ['admin']
            );
            throw $exception;
        }

        return $run;
    }

    public function sectionsFromChange(?array $fields): ?array
    {
        if ($fields === null) {
            return null;
        }
        if ($fields === []) {
            return [];
        }

        $joined = strtolower(implode(' ', array_map('strval', $fields)));
        $needles = ['vehicle', 'advert', 'media', 'image', 'feature', 'highlight', 'price', 'reservation', 'lifecycle', 'metadata', 'history', 'retail', 'stock'];
        $relevant = false;
        foreach ($needles as $needle) {
            if (str_contains($joined, $needle)) {
                $relevant = true;
                break;
            }
        }
        if (! $relevant) {
            return [];
        }

        return [
            'images' => str_contains($joined, 'image') || str_contains($joined, 'media'),
            'features' => str_contains($joined, 'feature'),
            'highlights' => str_contains($joined, 'highlight'),
            'prices' => str_contains($joined, 'price') || str_contains($joined, 'advert') || str_contains($joined, 'retail'),
            'core' => (bool) preg_match('/vehicle|metadata|lifecycle|history|reservation|stock/', $joined),
        ];
    }

    private function upsert(Dealer $dealer, array $item, array $context, Client $client): ?Vehicle
    {
        $stockId = (string) ($item['metadata']['stockId'] ?? $item['stockId'] ?? '');
        if ($stockId === '') {
            return null;
        }

        $sections = $context['sections'];
        $all = $sections === null;
        $vehicle = Vehicle::query()->where('at_stock_id', $stockId)->first();
        $previousPrice = $vehicle?->cash_price_pence;

        if ($vehicle && $vehicle->environment !== $dealer->integration->environment) {
            throw new AutotraderException('Refusing to mix sandbox and production stock for '.$stockId, needsAction: true);
        }

        if (! $vehicle) {
            $vehicle = new Vehicle([
                'dealer_id' => $dealer->id,
                'at_stock_id' => $stockId,
                'source' => 'autotrader',
                'environment' => $dealer->integration->environment,
                'local_hold' => 'none',
            ]);
        }

        $mapped = $this->map($item);
        if ($all || ($sections['core'] ?? false)) {
            $vehicle->fill($mapped['core']);
        }
        if ($all || ($sections['prices'] ?? false)) {
            $vehicle->fill($mapped['prices']);
        }
        $vehicle->missing_from_baseline_at = null;
        if (! empty($context['notification_time'])) {
            $vehicle->last_notification_at = $context['notification_time'];
        }
        $this->status->apply($vehicle);
        $vehicle->save();

        if (! $vehicle->slug) {
            $base = Str::slug(trim($vehicle->make.' '.$vehicle->model.' '.$vehicle->year)) ?: 'car';
            $vehicle->slug = $base.'-'.$vehicle->id;
            $vehicle->save();
        }

        VehiclePayload::query()->create([
            'vehicle_id' => $vehicle->id,
            'sync_run_id' => $context['sync_run_id'] ?? null,
            'webhook_event_id' => $context['webhook_event_id'] ?? null,
            'body' => $item,
            'fetched_at' => now(),
        ]);

        if ($all || ($sections['images'] ?? false)) {
            $vehicle->images()->delete();
            foreach ($mapped['images'] as $index => $url) {
                VehicleImage::query()->create([
                    'vehicle_id' => $vehicle->id,
                    'sort_order' => $index,
                    'url' => $url,
                ]);
            }
        }

        if ($all || ($sections['features'] ?? false)) {
            $vehicle->features()->delete();
            foreach ($mapped['features'] as $index => $feature) {
                VehicleFeature::query()->create([
                    'vehicle_id' => $vehicle->id,
                    'kind' => $feature['kind'],
                    'name' => $feature['name'],
                    'sort_order' => $index,
                ]);
            }
        }

        if ($all || ($sections['highlights'] ?? false)) {
            $vehicle->highlights()->delete();
            foreach ($mapped['highlights'] as $index => $name) {
                VehicleHighlight::query()->create([
                    'vehicle_id' => $vehicle->id,
                    'name' => $name,
                    'sort_order' => $index,
                ]);
            }
        }

        $newPrice = $vehicle->cash_price_pence;
        if ($newPrice !== null && $newPrice !== $previousPrice && ($all || ($sections['prices'] ?? false))) {
            VehiclePriceHistory::query()->create([
                'vehicle_id' => $vehicle->id,
                'cash_price_pence' => $newPrice,
                'source' => $context['source'] === 'webhook' ? 'webhook' : 'sync',
                'sync_run_id' => $context['sync_run_id'] ?? null,
                'webhook_event_id' => $context['webhook_event_id'] ?? null,
                'recorded_at' => now(),
            ]);
        }

        if ($all || ($sections['core'] ?? false) || ($sections['features'] ?? false)) {
            $this->enhanced($client, $dealer, $vehicle->fresh(), $mapped['feature_names']);
        }

        return $vehicle;
    }

    private function enhanced(Client $client, Dealer $dealer, Vehicle $vehicle, array $featureNames): void
    {
        if (! $vehicle->at_stock_id || ! $vehicle->derivative_id) {
            return;
        }

        $fingerprint = hash('sha256', implode('|', $featureNames));
        $fetch = VehicleTaxonomyFetch::query()->where('vehicle_id', $vehicle->id)->first();
        $featuresChanged = ! $fetch
            || $fetch->derivative_id !== $vehicle->derivative_id
            || $fetch->features_fingerprint !== $fingerprint;

        if ($featuresChanged) {
            try {
                $payload = $client->get('stock_features', $this->stockPath('features', $vehicle), [
                    'advertiserId' => $dealer->advertiser_id,
                ], $dealer->advertiser_id);
                $rows = $payload['features'] ?? $payload['results'] ?? [];
                if ($rows !== []) {
                    $vehicle->features()->where('kind', 'standard')->delete();
                    $optional = $vehicle->features()->where('kind', 'optional')->pluck('name');
                    $order = 0;
                    foreach ($rows as $feature) {
                        $name = trim((string) (is_array($feature) ? ($feature['name'] ?? '') : $feature));
                        if ($name === '') {
                            continue;
                        }
                        $type = strtolower((string) (is_array($feature) ? ($feature['type'] ?? 'standard') : 'standard'));
                        $kind = str_contains($type, 'option') ? 'optional' : 'standard';
                        if ($kind === 'optional' && $optional->contains($name)) {
                            continue;
                        }
                        VehicleFeature::query()->create([
                            'vehicle_id' => $vehicle->id,
                            'kind' => $kind,
                            'name' => mb_substr($name, 0, 255),
                            'sort_order' => $order,
                        ]);
                        $order++;
                    }
                }
                VehicleTaxonomyFetch::query()->updateOrCreate(
                    ['vehicle_id' => $vehicle->id],
                    [
                        'derivative_id' => $vehicle->derivative_id,
                        'features_fingerprint' => $fingerprint,
                        'fetched_at' => now(),
                    ],
                );
            } catch (AutotraderException) {
                // The stock record is already saved. A features failure must not wipe it.
            }
        }

        if (! preg_match('/electric|hybrid|phev/i', (string) $vehicle->fuel)) {
            return;
        }

        $chargeFetch = VehicleChargeTimeFetch::query()->where('vehicle_id', $vehicle->id)->first();
        if ($chargeFetch && $chargeFetch->derivative_id === $vehicle->derivative_id) {
            return;
        }

        try {
            $payload = $client->get('stock_charge_times', $this->stockPath('charge_times', $vehicle), [
                'advertiserId' => $dealer->advertiser_id,
            ], $dealer->advertiser_id);
            $rows = $payload['chargeTimes'] ?? $payload['results'] ?? [];
            $vehicle->chargeTimes()->delete();
            $count = 0;
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                VehicleChargeTime::query()->create([
                    'vehicle_id' => $vehicle->id,
                    'derivative_id' => $vehicle->derivative_id,
                    'charger_type' => mb_substr((string) ($row['chargerType'] ?? $row['charger_type'] ?? 'Charger'), 0, 80),
                    'duration_minutes' => isset($row['durationMinutes']) ? (int) $row['durationMinutes'] : (isset($row['duration_minutes']) ? (int) $row['duration_minutes'] : null),
                    'fetched_at' => now(),
                ]);
                $count++;
            }
            VehicleChargeTimeFetch::query()->updateOrCreate(
                ['vehicle_id' => $vehicle->id],
                [
                    'derivative_id' => $vehicle->derivative_id,
                    'result_count' => $count,
                    'fetched_at' => now(),
                ],
            );
        } catch (AutotraderException) {
            // Leave the previous charge-time fetch in place when the call cannot be made.
        }
    }

    private function map(array $item): array
    {
        $vehicle = $item['vehicle'] ?? [];
        $retail = $item['adverts']['retailAdverts'] ?? [];
        $media = $item['media'] ?? [];
        $price = $retail['price'] ?? [];
        $supplied = $retail['suppliedPrice'] ?? ($retail['suppliedPrice'] ?? null);
        $total = $retail['totalPrice'] ?? null;
        $admin = $retail['adminFee'] ?? ($retail['adminFee'] ?? null);

        $images = [];
        foreach ($media['images'] ?? [] as $image) {
            $url = is_array($image) ? ($image['href'] ?? $image['url'] ?? null) : $image;
            if (is_string($url) && $url !== '') {
                $images[] = mb_substr($url, 0, 500);
            }
        }

        $features = [];
        $featureNames = [];
        foreach ($item['features'] ?? [] as $index => $feature) {
            $name = is_array($feature) ? ($feature['name'] ?? '') : (string) $feature;
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $type = strtolower((string) (is_array($feature) ? ($feature['type'] ?? $feature['category'] ?? 'standard') : 'standard'));
            $features[] = [
                'kind' => str_contains($type, 'option') ? 'optional' : 'standard',
                'name' => mb_substr($name, 0, 255),
            ];
            $featureNames[] = $name;
        }
        sort($featureNames);

        $highlights = [];
        foreach ($item['highlights'] ?? [] as $highlight) {
            $name = is_array($highlight) ? ($highlight['name'] ?? '') : (string) $highlight;
            $name = trim((string) $name);
            if ($name !== '') {
                $highlights[] = mb_substr($name, 0, 255);
            }
        }

        $advertStatus = $retail['advertiserAdvert']['status'] ?? null;

        return [
            'core' => [
                'at_search_id' => $this->clip($item['metadata']['searchId'] ?? null, 64),
                'external_stock_id' => $this->clip($item['metadata']['externalStockId'] ?? null, 64),
                'external_stock_reference' => $this->clip($item['metadata']['externalStockReference'] ?? null, 64),
                'registration' => $this->clip($vehicle['registration'] ?? null, 16),
                'vin' => $this->clip($vehicle['vin'] ?? null, 32),
                'derivative_id' => $this->clip($vehicle['derivativeId'] ?? null, 64),
                'make' => $this->clip($vehicle['make'] ?? null, 80),
                'model' => $this->clip($vehicle['model'] ?? null, 80),
                'trim' => $this->clip($vehicle['derivative'] ?? $vehicle['trim'] ?? null, 160),
                'year' => $vehicle['yearOfManufacture'] ?? $vehicle['year'] ?? null,
                'mileage' => $vehicle['odometerReadingMiles'] ?? $vehicle['mileage'] ?? null,
                'fuel' => $this->clip($vehicle['fuelType'] ?? $vehicle['fuel'] ?? null, 80),
                'transmission' => $this->clip($vehicle['transmissionType'] ?? $vehicle['transmission'] ?? null, 80),
                'body' => $this->clip($vehicle['bodyType'] ?? $vehicle['body'] ?? null, 80),
                'colour' => $this->clip($vehicle['colour'] ?? $vehicle['colour'] ?? null, 80),
                'doors' => $vehicle['doors'] ?? null,
                'seats' => $vehicle['seats'] ?? null,
                'engine_cc' => $vehicle['engineCapacityCC'] ?? $vehicle['engineCapacityCc'] ?? null,
                'co2_emissions' => $vehicle['co2EmissionGPKM'] ?? null,
                'owners' => $vehicle['owners'] ?? null,
                'history_previous_owners' => $item['history']['previousOwners'] ?? null,
                'mot_expiry' => $vehicle['motExpiryDate'] ?? null,
                'first_registration_on' => $vehicle['firstRegistrationDate'] ?? null,
                'attention_grabber' => $this->clip($retail['attentionGrabber'] ?? null, 255),
                'description' => $retail['description'] ?? null,
                'description_2' => $retail['description2'] ?? null,
                'video_url' => $this->clip($media['video']['href'] ?? null, 500),
                'spin_url' => $this->clip($media['spin']['href'] ?? null, 500),
                'advertiser_advert_status' => $this->clip($advertStatus, 40),
                'retail_adverts' => $retail ?: null,
                'at_lifecycle' => $this->clip($item['metadata']['lifecycleState'] ?? null, 40),
                'reservation_status' => $this->clip($item['reservation']['status'] ?? null, 40),
                'vat_status' => $this->clip($retail['vatStatus'] ?? null, 40),
                'price_indicator_rating' => $this->clip($retail['priceIndicatorRating'] ?? null, 40),
                'at_updated_at' => $item['metadata']['lastUpdated'] ?? null,
            ],
            'prices' => [
                'cash_price_pence' => $this->pounds($price),
                'supplied_price_pence' => $this->pounds($supplied),
                'admin_fee_pence' => $this->pounds($admin),
                'total_price_pence' => $this->pounds($total),
            ],
            'images' => $images,
            'features' => $features,
            'highlights' => $highlights,
            'feature_names' => $featureNames,
        ];
    }

    private function stockPath(string $name, Vehicle $vehicle): string
    {
        return str_replace('{stockId}', rawurlencode((string) $vehicle->at_stock_id), (string) config('autotrader.paths.'.$name));
    }

    private function hideMissing(Dealer $dealer, string $environment, array $seen): void
    {
        $query = Vehicle::query()
            ->where('dealer_id', $dealer->id)
            ->where('source', 'autotrader')
            ->where('environment', $environment)
            ->whereNull('missing_from_baseline_at');

        if ($seen !== []) {
            $query->whereNotIn('at_stock_id', $seen);
        }

        $query->orderBy('id')->each(function (Vehicle $vehicle) {
            $vehicle->missing_from_baseline_at = now();
            $this->status->apply($vehicle);
            $vehicle->save();
        });
    }

    private function unwrap(array $payload): array
    {
        if (isset($payload['metadata'])) {
            return $payload;
        }
        foreach (['stock', 'result'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $this->unwrap($payload[$key]);
            }
        }
        if (isset($payload['results'][0]) && is_array($payload['results'][0])) {
            return $payload['results'][0];
        }

        return $payload;
    }

    private function pounds(mixed $value): ?int
    {
        if (is_array($value)) {
            $value = $value['amountGBP'] ?? $value['amount'] ?? null;
        }
        if ($value === null || $value === '') {
            return null;
        }

        return (int) round(((float) $value) * 100);
    }

    private function clip(mixed $value, int $length): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr((string) $value, 0, $length);
    }

    private function guardEnvironment(Integration $integration): void
    {
        $configured = (string) config('autotrader.environment');
        if (! in_array($configured, ['sandbox', 'production'], true) || $integration->environment !== $configured) {
            throw new AutotraderException('Sandbox and production credentials must not be used against the other environment.', needsAction: true);
        }
    }
}
