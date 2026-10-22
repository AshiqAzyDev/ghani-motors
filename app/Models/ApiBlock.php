<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiBlock extends Model
{
    protected $table = 'api_blocks';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'pause_until' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
