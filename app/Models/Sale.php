<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    protected $table = 'sales';

    public const UPDATED_AT = null;

    protected $guarded = ['completed_vehicle_key'];

    protected function casts(): array
    {
        return ['sold_on' => 'date'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function partExchangeValuation(): BelongsTo
    {
        return $this->belongsTo(Valuation::class, 'part_exchange_valuation_id');
    }
}
