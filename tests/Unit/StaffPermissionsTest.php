<?php

namespace Tests\Unit;

use App\Models\StaffUser;
use App\Support\StaffPermissions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffPermissionsTest extends TestCase
{
    #[Test]
    public function viewer_preset_is_view_only(): void
    {
        $staff = new StaffUser([
            'role' => 'viewer',
            'is_active' => true,
            'permissions' => null,
        ]);

        $this->assertFalse($staff->can('vehicles.edit_manual'));
        $this->assertFalse($staff->can('staff.manage'));
        $this->assertFalse($staff->can('sync.run'));
        $this->assertFalse($staff->canMutate());
        $this->assertTrue($staff->can('vehicles.view'));
        $this->assertTrue($staff->can('leads.view'));
        $this->assertSame(StaffPermissions::preset('viewer'), $staff->permissionList());
    }

    #[Test]
    public function sales_preset_can_manage_day_to_day_but_not_staff_settings_or_sync(): void
    {
        $staff = new StaffUser([
            'role' => 'sales',
            'is_active' => true,
            'permissions' => null,
        ]);

        $this->assertTrue($staff->can('vehicles.edit_manual'));
        $this->assertTrue($staff->can('vehicles.feature'));
        $this->assertTrue($staff->can('bookings.manage'));
        $this->assertTrue($staff->can('invoices.manage'));
        $this->assertTrue($staff->canMutate());
        $this->assertFalse($staff->can('staff.manage'));
        $this->assertFalse($staff->can('sync.run'));
        $this->assertFalse($staff->can('settings.view'));
        $this->assertFalse($staff->can('settings.manage'));
    }

    #[Test]
    public function settings_abilities_are_admin_only_even_if_listed_on_custom_permissions(): void
    {
        $staff = new StaffUser([
            'role' => 'sales',
            'is_active' => true,
            'permissions' => ['vehicles.view', 'settings.view', 'settings.manage'],
        ]);

        $this->assertTrue($staff->can('vehicles.view'));
        $this->assertFalse($staff->can('settings.view'));
        $this->assertFalse($staff->can('settings.manage'));
    }

    #[Test]
    public function admin_has_full_access(): void
    {
        $staff = new StaffUser([
            'role' => 'admin',
            'is_active' => true,
            'permissions' => null,
        ]);

        $this->assertTrue($staff->isAdmin());
        $this->assertTrue($staff->can('staff.manage'));
        $this->assertTrue($staff->can('sync.run'));
        $this->assertTrue($staff->canMutate());
        $this->assertSame(StaffPermissions::ALL, $staff->permissionList());
    }

    #[Test]
    public function custom_permissions_override_role_preset(): void
    {
        $staff = new StaffUser([
            'role' => 'viewer',
            'is_active' => true,
            'permissions' => ['vehicles.view', 'vehicles.feature', 'sync.run'],
        ]);

        $this->assertTrue($staff->can('vehicles.feature'));
        $this->assertTrue($staff->can('sync.run'));
        $this->assertTrue($staff->canMutate());
        $this->assertFalse($staff->can('bookings.view'));
    }

    #[Test]
    public function inactive_staff_cannot_do_anything(): void
    {
        $staff = new StaffUser([
            'role' => 'admin',
            'is_active' => false,
            'permissions' => null,
        ]);

        $this->assertFalse($staff->can('staff.manage'));
        $this->assertFalse($staff->canMutate());
    }
}
