<?php

namespace App\Models;

use App\Notifications\StaffResetPassword;
use App\Support\StaffPermissions;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class StaffUser extends Authenticatable implements CanResetPasswordContract
{
    use CanResetPassword, Notifiable;

    protected $table = 'staff_users';

    protected $guarded = [];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'permissions' => 'array',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getEmailForPasswordReset(): string
    {
        return (string) $this->email;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new StaffResetPassword($token));
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function canMutate(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->isAdmin()
            || $this->can('bookings.manage')
            || $this->can('customers.manage')
            || $this->can('leads.manage')
            || $this->can('sales.manage')
            || $this->can('invoices.manage')
            || $this->can('vehicles.edit_manual')
            || $this->can('vehicles.feature')
            || $this->can('settings.manage')
            || $this->can('policies.manage')
            || $this->can('sync.run')
            || $this->can('staff.manage');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function permissionList(): array
    {
        if ($this->isAdmin()) {
            return StaffPermissions::ALL;
        }

        // null / empty = role preset (legacy rows and unchecked form posts).
        // Only an explicit non-empty list overrides the preset.
        $stored = is_array($this->permissions) ? $this->permissions : null;
        $list = ($stored !== null && $stored !== [])
            ? array_values(array_intersect(StaffPermissions::ALL, $stored))
            : StaffPermissions::preset($this->role ?: 'viewer');

        return array_values(array_diff($list, StaffPermissions::adminOnly()));
    }

    public function can($ability, $arguments = []): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->isAdmin()) {
            return true;
        }
        if (! is_string($ability)) {
            return false;
        }
        if (in_array($ability, StaffPermissions::adminOnly(), true)) {
            return false;
        }

        return in_array($ability, $this->permissionList(), true);
    }

    public function cannot($ability, $arguments = []): bool
    {
        return ! $this->can($ability, $arguments);
    }
}
