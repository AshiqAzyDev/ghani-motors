<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleHighlight extends Model
{
    protected $table = 'vehicle_highlights';

    public $timestamps = false;

    protected $guarded = [];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
