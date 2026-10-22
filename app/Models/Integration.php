<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Integration extends Model
{
    protected $table = 'integrations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'credentials_last_used_at' => 'datetime',
            'notifications_paused' => 'boolean',
        ];
    }

    public function dealers(): HasMany
    {
        return $this->hasMany(Dealer::class);
    }
}
