<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $table = 'webhook_events';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'change_fields' => 'array',
            'raw_body' => 'array',
            'signature_valid' => 'boolean',
            'notification_time' => 'datetime',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
