<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Mail\GhaniMailer;
use Illuminate\Console\Command;

class SendBookingReminders extends Command
{
    protected $signature = 'mail:booking-reminders';

    protected $description = 'Email customers about reservations and appointments due tomorrow';

    public function handle(GhaniMailer $mailer): int
    {
        $tomorrow = now()->addDay()->toDateString();
        $sent = 0;

        $reservations = Booking::query()
            ->with(['customer', 'vehicle'])
            ->where('type', 'reservation')
            ->where('status', 'held')
            ->whereDate('preferred_on', $tomorrow)
            ->get();

        foreach ($reservations as $booking) {
            if (! $booking->customer || ! $booking->vehicle) {
                continue;
            }
            $mailer->reservationReminder($booking->customer, $booking, $booking->vehicle);
            $sent++;
        }

        $appointments = Booking::query()
            ->with(['customer', 'vehicle'])
            ->whereIn('type', ['viewing', 'test_drive'])
            ->where('status', 'confirmed')
            ->whereDate('preferred_on', $tomorrow)
            ->get();

        foreach ($appointments as $booking) {
            if (! $booking->customer || ! $booking->vehicle) {
                continue;
            }
            $mailer->appointmentReminder($booking->customer, $booking, $booking->vehicle);
            $sent++;
        }

        $this->info("Sent {$sent} booking reminder".($sent === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
