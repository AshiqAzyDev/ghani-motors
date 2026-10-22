<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehiclePayload extends Model
{
    protected $table = 'vehicle_payloads';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'body' => 'array',
            'fetched_at' => 'datetime',
        ];
    }
}
