<?php

namespace App\Models;

use App\Notifications\CustomerResetPassword;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable implements CanResetPasswordContract
{
    use CanResetPassword, Notifiable;

    protected $table = 'customers';

    protected $guarded = [];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return ['password_hash' => 'hashed'];
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomerResetPassword($token));
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function savedVehicles(): HasMany
    {
        return $this->hasMany(SavedVehicle::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
