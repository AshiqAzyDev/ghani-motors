<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $table = 'bookings';

    protected $guarded = ['held_reservation_key', 'active_test_drive_key'];

    protected function casts(): array
    {
        return ['preferred_on' => 'date'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return list<string>
     */
    public function nextStatuses(): array
    {
        return match ($this->type) {
            'reservation' => $this->status === 'held' ? ['cancelled', 'completed'] : [],
            'viewing', 'test_drive' => match ($this->status) {
                'requested' => ['confirmed', 'cancelled'],
                'confirmed' => ['completed', 'cancelled'],
                default => [],
            },
            default => [],
        };
    }
}
