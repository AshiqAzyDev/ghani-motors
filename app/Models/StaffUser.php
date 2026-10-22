<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class StaffUser extends Authenticatable
{
    protected $table = 'staff_users';

    protected $guarded = [];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function canMutate(): bool
    {
        return $this->is_active && in_array($this->role, ['admin', 'sales'], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
