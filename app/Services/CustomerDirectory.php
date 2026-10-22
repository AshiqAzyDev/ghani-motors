<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\SavedVehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerDirectory
{
    public function findOrCreate(Dealer $dealer, string $name, string $email, string $phone, string $source, ?string $postcode = null): Customer
    {
        $email = strtolower(trim($email));
        $existing = Customer::query()->where('email', $email)->first();

        if ($existing) {
            if ((int) $existing->dealer_id !== (int) $dealer->id) {
                throw ValidationException::withMessages([
                    'email' => 'That email is already used.',
                ]);
            }

            if ($existing->phone === '' && $phone !== '') {
                $existing->phone = $phone;
                $existing->save();
            }

            return $existing;
        }

        return Customer::query()->create([
            'dealer_id' => $dealer->id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'postcode' => $postcode,
            'source' => $source,
        ]);
    }

    public function mergeSaved(Customer $customer, array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return;
        }

        $allowed = $customer->dealer->vehicles()->public()->whereIn('id', $ids)->pluck('id');
        foreach ($allowed as $vehicleId) {
            SavedVehicle::query()->firstOrCreate([
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicleId,
            ]);
        }
    }

    public function log(?int $staffId, ?int $vehicleId, ?int $customerId, string $message): void
    {
        ActivityLog::query()->create([
            'staff_user_id' => $staffId,
            'vehicle_id' => $vehicleId,
            'customer_id' => $customerId,
            'message' => mb_substr($message, 0, 500),
        ]);
    }

    public function savedIds(?Customer $customer): array
    {
        $session = array_map('intval', (array) session('saved_vehicle_ids', []));
        if (! $customer) {
            return $session;
        }

        return $customer->savedVehicles()->pluck('vehicle_id')->map(fn ($id) => (int) $id)->all();
    }

    public function toggleSaved(Dealer $dealer, int $vehicleId, ?Customer $customer): bool
    {
        $vehicle = $dealer->vehicles()->public()->whereKey($vehicleId)->firstOrFail();

        if ($customer) {
            $existing = SavedVehicle::query()
                ->where('customer_id', $customer->id)
                ->where('vehicle_id', $vehicle->id)
                ->first();
            if ($existing) {
                $existing->delete();

                return false;
            }
            SavedVehicle::query()->create([
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
            ]);

            return true;
        }

        $ids = array_map('intval', (array) session('saved_vehicle_ids', []));
        if (in_array($vehicle->id, $ids, true)) {
            session(['saved_vehicle_ids' => array_values(array_diff($ids, [$vehicle->id]))]);

            return false;
        }

        $ids[] = $vehicle->id;
        session(['saved_vehicle_ids' => $ids]);

        return true;
    }

    public function nextInvoiceNumber(Dealer $dealer): string
    {
        return DB::transaction(function () use ($dealer) {
            $counter = \App\Models\InvoiceCounter::query()
                ->where('dealer_id', $dealer->id)
                ->lockForUpdate()
                ->firstOrFail();
            $number = (int) $counter->next_number;
            $counter->next_number = $number + 1;
            $counter->save();

            return 'GM-'.$number;
        });
    }
}
