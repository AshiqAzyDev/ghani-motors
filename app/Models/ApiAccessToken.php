<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiAccessToken extends Model
{
    protected $table = 'api_access_tokens';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'obtained_at' => 'datetime',
            'expires_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }
}
