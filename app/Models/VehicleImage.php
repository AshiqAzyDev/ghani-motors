<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleImage extends Model
{
    protected $table = 'vehicle_images';

    public $timestamps = false;

    protected $guarded = [];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function sized(string $size = 'w800h600'): string
    {
        return str_replace('{resize}', $size, (string) $this->url);
    }
}
