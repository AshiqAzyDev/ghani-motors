<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehiclePriceHistory extends Model
{
    protected $table = 'vehicle_price_history';

    public $timestamps = false;

    protected $guarded = [];
}
