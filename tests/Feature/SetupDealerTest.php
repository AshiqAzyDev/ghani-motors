<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\StaffUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SetupDealerTest extends TestCase
{
    #[Test]
    public function home_redirects_to_setup_when_no_dealer_exists(): void
    {
        $this->installSetupSchema();

        $this->get('/')
            ->assertRedirect(route('setup.dealer'));
    }

    #[Test]
    public function home_redirects_to_setup_when_dealers_table_is_missing(): void
    {
        Schema::dropIfExists('invoice_counters');
        Schema::dropIfExists('staff_users');
        Schema::dropIfExists('dealers');
        Schema::dropIfExists('integrations');

        $this->get('/')
            ->assertRedirect(route('setup.dealer'));
    }

    #[Test]
    public function setup_form_is_shown_when_no_dealer_exists(): void
    {
        $this->installSetupSchema();

        $this->get(route('setup.dealer'))
            ->assertOk()
            ->assertSee('Dealership setup');
    }

    #[Test]
    public function setup_can_create_the_first_dealer_and_admin(): void
    {
        $this->installSetupSchema();

        $this->post(route('setup.dealer.store'), [
            'name' => 'Ghani Motors Ltd',
            'trading_name' => 'Ghani Motors',
            'phone' => '020 8592 1234',
            'email' => 'hello@ghanimotors.co.uk',
            'address_line' => 'Rainham Road South',
            'city' => 'Dagenham',
            'postcode' => 'RM10 8YT',
            'tagline' => 'QUALITY USED CARS',
            'opening_hours' => 'Mon–Sat 9am–6pm',
            'website_host' => 'localhost',
            'admin_name' => 'Site Admin',
            'admin_email' => 'admin@ghanimotors.co.uk',
            'admin_password' => 'Password1!',
            'admin_password_confirmation' => 'Password1!',
        ])->assertRedirect(route('home'));

        $this->assertDatabaseHas('dealers', [
            'name' => 'Ghani Motors Ltd',
            'trading_name' => 'Ghani Motors',
            'website_host' => 'localhost',
        ]);

        $admin = StaffUser::query()->where('email', 'admin@ghanimotors.co.uk')->first();
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('Password1!', $admin->password_hash));
        $this->assertDatabaseHas('invoice_counters', [
            'dealer_id' => Dealer::query()->firstOrFail()->id,
            'next_number' => 1001,
        ]);
    }

    #[Test]
    public function setup_is_blocked_once_a_dealer_exists(): void
    {
        $this->installSetupSchema();

        Dealer::query()->create([
            'integration_id' => 1,
            'name' => 'Existing Dealer',
            'website_host' => 'localhost',
        ]);

        $this->get(route('setup.dealer'))
            ->assertRedirect(route('home'));

        $this->post(route('setup.dealer.store'), [
            'name' => 'Another Dealer',
            'admin_name' => 'Admin',
            'admin_email' => 'admin2@example.com',
            'admin_password' => 'Password1!',
            'admin_password_confirmation' => 'Password1!',
        ])->assertForbidden();
    }

    #[Test]
    public function setup_store_is_forbidden_when_dealer_existence_check_fails(): void
    {
        $this->installSetupSchema();

        Schema::shouldReceive('hasTable')
            ->with('dealers')
            ->andThrow(new \RuntimeException('database unavailable'));

        $this->post(route('setup.dealer.store'), [
            'name' => 'Ghani Motors Ltd',
            'admin_name' => 'Site Admin',
            'admin_email' => 'admin@ghanimotors.co.uk',
            'admin_password' => 'Password1!',
            'admin_password_confirmation' => 'Password1!',
        ])->assertForbidden();

        $this->assertDatabaseCount('dealers', 0);
    }

    private function installSetupSchema(): void
    {
        foreach (['invoice_counters', 'staff_users', 'dealers', 'integrations'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('environment')->default('sandbox');
            $table->timestamps();
        });

        Schema::create('dealers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integration_id');
            $table->string('name');
            $table->string('trading_name')->nullable();
            $table->string('tagline')->nullable();
            $table->string('advertiser_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->string('postcode')->nullable();
            $table->string('website_host')->nullable();
            $table->string('opening_hours')->nullable();
            $table->boolean('on_integration')->default(true);
            $table->timestamps();
        });

        Schema::create('staff_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dealer_id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->string('role')->default('sales');
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('invoice_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dealer_id')->unique();
            $table->unsignedInteger('next_number')->default(1001);
        });
    }
}
