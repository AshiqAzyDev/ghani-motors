<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Vehicle;

class VehicleStatus
{
    /**
     * Public cars are Autotrader stock that is published and available or reserved.
     * Manual cars stay off the website. A completed local sale stays sold.
     */
    public function apply(Vehicle $vehicle): Vehicle
    {
        [$status, $show] = $this->resolve($vehicle);
        $vehicle->website_status = $status;
        $vehicle->show_on_website = $show;

        return $vehicle;
    }

    public function resolve(Vehicle $vehicle): array
    {
        if ($vehicle->source === 'manual') {
            $status = $vehicle->website_status;
            if ($vehicle->local_hold === 'preparation') {
                $status = 'preparation';
            } elseif ($vehicle->local_hold === 'reserved') {
                $status = 'reserved';
            } elseif (! in_array($status, ['available', 'preparation', 'sold', 'reserved'], true)) {
                $status = 'hidden';
            }

            return [$status, false];
        }

        $soldLocally = $vehicle->exists && Sale::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('status', 'completed')
            ->exists();

        if ($soldLocally || strtoupper((string) $vehicle->at_lifecycle) === 'SOLD') {
            return ['sold', false];
        }

        $published = strtoupper((string) $vehicle->advertiser_advert_status) === 'PUBLISHED';
        $lifecycle = strtoupper((string) $vehicle->at_lifecycle);

        if (! $published || in_array($lifecycle, ['DELETED', 'WASTEBIN'], true) || $vehicle->missing_from_baseline_at) {
            return ['hidden', false];
        }

        if (! $vehicle->make || ! $vehicle->model || $vehicle->publicPricePence() === null) {
            return ['hidden', false];
        }

        if ($vehicle->local_hold === 'preparation') {
            return ['preparation', false];
        }

        $atReserved = in_array(strtoupper((string) $vehicle->reservation_status), ['RESERVED', 'SALE_IN_PROGRESS'], true)
            || $lifecycle === 'SALE_IN_PROGRESS';

        if ($vehicle->local_hold === 'reserved' || $atReserved) {
            return ['reserved', true];
        }

        if ($lifecycle === '' || $lifecycle === 'FORECOURT') {
            return ['available', true];
        }

        return ['hidden', false];
    }
}
