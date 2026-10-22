<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Vehicle extends Model
{
    protected $table = 'vehicles';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'show_on_website' => 'boolean',
            'retail_adverts' => 'array',
            'mot_expiry' => 'date',
            'first_registration_on' => 'date',
            'at_updated_at' => 'datetime',
            'last_notification_at' => 'datetime',
            'missing_from_baseline_at' => 'datetime',
        ];
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(VehicleImage::class)->orderBy('sort_order');
    }

    public function features(): HasMany
    {
        return $this->hasMany(VehicleFeature::class)->orderBy('sort_order');
    }

    public function highlights(): HasMany
    {
        return $this->hasMany(VehicleHighlight::class)->orderBy('sort_order');
    }

    public function chargeTimes(): HasMany
    {
        return $this->hasMany(VehicleChargeTime::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function completedSale(): HasOne
    {
        return $this->hasOne(Sale::class)->where('status', 'completed');
    }

    public function publicPricePence(): ?int
    {
        if ($this->total_price_pence !== null) {
            return (int) $this->total_price_pence;
        }

        if ($this->supplied_price_pence !== null) {
            return (int) $this->supplied_price_pence;
        }

        return $this->cash_price_pence !== null ? (int) $this->cash_price_pence : null;
    }

    public function title(): string
    {
        return trim(implode(' ', array_filter([$this->year, $this->make, $this->model, $this->trim])));
    }

    public function scopePublic($query)
    {
        return $query->where('show_on_website', true)
            ->whereIn('website_status', ['available', 'reserved']);
    }
}
