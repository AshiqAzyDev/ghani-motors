<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function markRead(): void
    {
        if ($this->read_at) {
            return;
        }
        $this->read_at = now();
        $this->save();
    }
}
