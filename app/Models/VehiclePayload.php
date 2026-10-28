<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehiclePayload extends Model
{
    protected $table = 'vehicle_payloads';

    // Table has fetched_at only — no created_at / updated_at columns.
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'body' => 'array',
            'fetched_at' => 'datetime',
        ];
    }
}
