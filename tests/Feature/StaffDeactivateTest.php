<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\StaffUser;
use App\Support\StaffPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffDeactivateTest extends TestCase
{
    #[Test]
    public function admin_can_deactivate_another_staff_member(): void
    {
        $this->installSchema();
        $dealer = $this->seedDealer();

        $admin = StaffUser::query()->create([
            'dealer_id' => $dealer->id,
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'password_hash' => 'Password1!',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $member = StaffUser::query()->create([
            'dealer_id' => $dealer->id,
            'name' => 'Sales Person',
            'email' => 'sales@example.com',
            'password_hash' => 'Password1!',
            'role' => 'sales',
            'permissions' => StaffPermissions::preset('sales'),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'staff')
            ->from(route('dealer.staff'))
            ->put(route('dealer.staff.update', $member), [
                'name' => $member->name,
                'role' => 'sales',
                'is_active' => '0',
            ])
            ->assertRedirect(route('dealer.staff'));

        $this->assertFalse($member->fresh()->is_active);
    }

    #[Test]
    public function staff_cannot_deactivate_themselves(): void
    {
        $this->installSchema();
        $dealer = $this->seedDealer();

        $admin = StaffUser::query()->create([
            'dealer_id' => $dealer->id,
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'password_hash' => 'Password1!',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'staff')
            ->from(route('dealer.staff'))
            ->put(route('dealer.staff.update', $admin), [
                'name' => $admin->name,
                'role' => 'admin',
                'is_active' => '0',
            ])
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($admin->fresh()->is_active);
    }

    private function seedDealer(): Dealer
    {
        return Dealer::query()->create([
            'integration_id' => 1,
            'name' => 'Ghani Motors',
            'website_host' => 'localhost',
            'phone' => '020 0000 0000',
        ]);
    }

    private function installSchema(): void
    {
        foreach (['activity_log', 'staff_users', 'dealers', 'integrations'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('dealers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integration_id');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('website_host')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dealer_id');
            $table->string('name');
            $table->string('email');
            $table->string('password_hash');
            $table->string('role')->default('sales');
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_user_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->string('message');
            $table->timestamps();
        });
    }
}
