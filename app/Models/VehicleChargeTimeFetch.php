<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleChargeTimeFetch extends Model
{
    protected $table = 'vehicle_charge_time_fetches';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['fetched_at' => 'datetime'];
    }
}
