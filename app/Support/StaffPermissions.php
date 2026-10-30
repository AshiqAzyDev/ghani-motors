<?php

namespace App\Support;

class StaffPermissions
{
    public const ALL = [
        'vehicles.view',
        'vehicles.edit_manual',
        'vehicles.feature',
        'bookings.view',
        'bookings.manage',
        'customers.view',
        'customers.manage',
        'leads.view',
        'leads.manage',
        'sales.view',
        'sales.manage',
        'invoices.view',
        'invoices.manage',
        'settings.view',
        'settings.manage',
        'policies.view',
        'policies.manage',
        'sync.run',
        'staff.manage',
    ];

    public static function labels(): array
    {
        return [
            'vehicles.view' => 'View vehicles',
            'vehicles.edit_manual' => 'Edit manual / internal vehicle fields',
            'vehicles.feature' => 'Feature cars on homepage',
            'bookings.view' => 'View bookings',
            'bookings.manage' => 'Create and update bookings',
            'customers.view' => 'View customers',
            'customers.manage' => 'Add and edit customers',
            'leads.view' => 'View leads',
            'leads.manage' => 'Update leads, notes and requirements',
            'sales.view' => 'View sales',
            'sales.manage' => 'Record sales',
            'invoices.view' => 'View invoices',
            'invoices.manage' => 'Create and manage invoices',
            'settings.view' => 'View website settings',
            'settings.manage' => 'Edit website settings',
            'policies.view' => 'View compliance policies',
            'policies.manage' => 'Manage compliance policies',
            'sync.run' => 'Run Autotrader baseline sync',
            'staff.manage' => 'Manage staff and permissions',
        ];
    }

    public static function preset(string $role): array
    {
        return match ($role) {
            'admin' => self::ALL,
            'sales' => [
                'vehicles.view',
                'vehicles.edit_manual',
                'vehicles.feature',
                'bookings.view',
                'bookings.manage',
                'customers.view',
                'customers.manage',
                'leads.view',
                'leads.manage',
                'sales.view',
                'sales.manage',
                'invoices.view',
                'invoices.manage',
            ],
            default => [
                'vehicles.view',
                'bookings.view',
                'customers.view',
                'leads.view',
                'sales.view',
                'invoices.view',
            ],
        };
    }

    /**
     * Abilities that only admins may hold. Not shown on staff invite/edit forms.
     *
     * @return list<string>
     */
    public static function adminOnly(): array
    {
        return [
            'settings.view',
            'settings.manage',
            'policies.view',
            'policies.manage',
        ];
    }

    /**
     * Permissions an admin can assign to non-admin staff.
     *
     * @return array<string, string>
     */
    public static function assignableLabels(): array
    {
        return array_diff_key(self::labels(), array_flip(self::adminOnly()));
    }

    public static function navMap(): array
    {
        return [
            'dealer.home' => null,
            'dealer.vehicles' => 'vehicles.view',
            'dealer.bookings' => 'bookings.view',
            'dealer.customers' => 'customers.view',
            'dealer.leads' => 'leads.view',
            'dealer.sales' => 'sales.view',
            'dealer.invoices' => 'invoices.view',
            'dealer.policies' => 'policies.view',
            'dealer.settings' => 'settings.view',
            'dealer.staff' => 'staff.manage',
        ];
    }

    /**
     * @return array{admin: list<string>, sales: list<string>, viewer: list<string>}
     */
    public static function presets(): array
    {
        return [
            'admin' => self::preset('admin'),
            'sales' => self::preset('sales'),
            'viewer' => self::preset('viewer'),
        ];
    }
}
