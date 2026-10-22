<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiCallLog extends Model
{
    protected $table = 'api_call_log';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'retryable' => 'boolean',
            'needs_action' => 'boolean',
        ];
    }
}
