<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\StaffUser;
use App\Models\Vehicle;
use App\Services\Mail\GhaniMailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private VehicleStatus $status,
        private CustomerDirectory $directory,
        private GhaniMailer $mailer,
        private AppNotifier $notifier,
    ) {}

    public function reserve(Vehicle $vehicle, Customer $customer, string $date, ?string $notes, ?StaffUser $staff = null): Booking
    {
        return DB::transaction(function () use ($vehicle, $customer, $date, $notes, $staff) {
            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicle->id);
            if ($vehicle->website_status !== 'available' || $vehicle->local_hold !== 'none') {
                throw ValidationException::withMessages([
                    'vehicle' => 'This car can only be reserved while it is available.',
                ]);
            }

            $booking = Booking::query()->create([
                'vehicle_id' => $vehicle->id,
                'customer_id' => $customer->id,
                'type' => 'reservation',
                'status' => 'held',
                'preferred_on' => $date,
                'notes' => $notes,
                'created_by' => $staff?->id,
                'updated_by' => $staff?->id,
            ]);

            $vehicle->local_hold = 'reserved';
            $this->status->apply($vehicle);
            $vehicle->save();
            $this->directory->log($staff?->id, $vehicle->id, $customer->id, 'Reservation held for '.$vehicle->title());

            return $booking;
        });
    }

    public function testDrive(Vehicle $vehicle, Customer $customer, string $date, ?string $time, ?string $notes, ?StaffUser $staff = null): Booking
    {
        return $this->appointment($vehicle, $customer, 'test_drive', $date, $time, $notes, $staff);
    }

    public function viewing(Vehicle $vehicle, Customer $customer, string $date, ?string $time, ?string $notes, ?StaffUser $staff = null): Booking
    {
        return $this->appointment($vehicle, $customer, 'viewing', $date, $time, $notes, $staff);
    }

    public function appointment(Vehicle $vehicle, Customer $customer, string $type, string $date, ?string $time, ?string $notes, ?StaffUser $staff = null): Booking
    {
        if (! in_array($vehicle->website_status, ['available', 'reserved'], true)) {
            throw ValidationException::withMessages([
                'vehicle' => 'Viewings and test drives are open on available and reserved cars.',
            ]);
        }

        return Booking::query()->create([
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customer->id,
            'type' => $type,
            'status' => 'requested',
            'preferred_on' => $date,
            'preferred_time' => $time,
            'notes' => $notes,
            'created_by' => $staff?->id,
            'updated_by' => $staff?->id,
        ]);
    }

    public function transition(Booking $booking, string $to, StaffUser $staff): Booking
    {
        $allowed = $booking->nextStatuses();

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'That status change is not available.',
            ]);
        }

        $booking = DB::transaction(function () use ($booking, $to, $staff) {
            $booking->status = $to;
            $booking->updated_by = $staff->id;
            $booking->save();

            if ($booking->type === 'reservation' && $to === 'cancelled') {
                $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($booking->vehicle_id);
                $stillHeld = Booking::query()
                    ->where('vehicle_id', $vehicle->id)
                    ->where('type', 'reservation')
                    ->where('status', 'held')
                    ->exists();
                if (! $stillHeld && $vehicle->local_hold === 'reserved') {
                    $vehicle->local_hold = 'none';
                    $this->status->apply($vehicle);
                    $vehicle->save();
                }
            }

            $this->directory->log($staff->id, $booking->vehicle_id, $booking->customer_id, 'Booking '.$booking->id.' set to '.$to);

            return $booking;
        });

        try {
            $booking->loadMissing(['customer', 'vehicle.dealer']);
            if ($booking->customer && $booking->vehicle) {
                $this->mailer->bookingUpdated($booking->customer, $booking, $booking->vehicle);
                $this->notifier->notifyCustomer(
                    $booking->customer,
                    'booking.updated',
                    'Booking '.$to.': '.$booking->vehicle->title(),
                    ucfirst(str_replace('_', ' ', $booking->type)),
                    route('account')
                );
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $booking;
    }
}
