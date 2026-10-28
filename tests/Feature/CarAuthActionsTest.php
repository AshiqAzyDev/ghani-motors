<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Vehicle;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CarAuthActionsTest extends TestCase
{
    #[Test]
    public function guests_are_redirected_to_login_when_saving_a_car(): void
    {
        $this->from('/cars/demo-car')
            ->post(route('cars.save', 'demo-car'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function guests_are_redirected_to_login_when_reserving_a_car(): void
    {
        $this->from('/cars/demo-car')
            ->post(route('cars.reserve', 'demo-car'), [
                'name' => 'Guest',
                'email' => 'guest@example.com',
                'phone' => '07000000000',
                'preferred_on' => now()->addDay()->toDateString(),
                'accepted_terms' => '1',
            ])
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function guests_are_redirected_to_login_when_requesting_a_viewing(): void
    {
        $this->from('/cars/demo-car')
            ->post(route('cars.viewing', 'demo-car'), [
                'name' => 'Guest',
                'email' => 'guest@example.com',
                'phone' => '07000000000',
                'preferred_on' => now()->addDay()->toDateString(),
                'preferred_time' => '10:00',
                'accepted_terms' => '1',
            ])
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function authenticated_customers_can_save_a_car(): void
    {
        $this->installMinimalPublicSchema();
        Mail::fake();

        $dealer = Dealer::query()->create([
            'integration_id' => 1,
            'name' => 'Ghani Motors',
            'website_host' => 'localhost',
        ]);

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'name' => 'Alex Customer',
            'email' => 'alex@example.com',
            'phone' => '07000000001',
            'password_hash' => 'password',
            'source' => 'website_enquiry',
        ]);

        $car = Vehicle::query()->create([
            'dealer_id' => $dealer->id,
            'environment' => 'sandbox',
            'source' => 'manual',
            'show_on_website' => true,
            'slug' => 'demo-car',
            'make' => 'Ford',
            'model' => 'Focus',
            'website_status' => 'available',
            'local_hold' => 'none',
        ]);

        $this->actingAs($customer)
            ->postJson(route('cars.save', $car->slug))
            ->assertOk()
            ->assertJson([
                'saved' => true,
                'vehicle_id' => $car->id,
            ]);

        $this->assertDatabaseHas('saved_vehicles', [
            'customer_id' => $customer->id,
            'vehicle_id' => $car->id,
        ]);
    }

    #[Test]
    public function guests_can_still_send_a_car_enquiry(): void
    {
        $this->installMinimalPublicSchema();
        Mail::fake();

        $dealer = Dealer::query()->create([
            'integration_id' => 1,
            'name' => 'Ghani Motors',
            'website_host' => 'localhost',
        ]);

        Vehicle::query()->create([
            'dealer_id' => $dealer->id,
            'environment' => 'sandbox',
            'source' => 'manual',
            'show_on_website' => true,
            'slug' => 'demo-car',
            'make' => 'Ford',
            'model' => 'Focus',
            'website_status' => 'available',
            'local_hold' => 'none',
        ]);

        $this->from('/cars/demo-car')
            ->post(route('cars.enquiry', 'demo-car'), [
                'name' => 'Guest',
                'email' => 'guest@example.com',
                'phone' => '07000000000',
                'message' => 'Is this still available?',
                'accepted_terms' => '1',
            ])
            ->assertRedirect('/cars/demo-car');

        $this->assertDatabaseHas('customers', [
            'email' => 'guest@example.com',
        ]);
        $this->assertDatabaseHas('leads', [
            'subject' => 'Enquiry: Ford Focus',
        ]);
    }

    private function installMinimalPublicSchema(): void
    {
        foreach ([
            'app_notifications', 'leads', 'saved_vehicles', 'vehicles', 'customers', 'staff_users', 'dealers', 'integrations',
        ] as $table) {
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
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dealer_id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('password_hash')->nullable();
            $table->string('address')->nullable();
            $table->string('postcode')->nullable();
            $table->text('notes')->nullable();
            $table->string('source')->default('website_enquiry');
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dealer_id');
            $table->string('environment')->default('sandbox');
            $table->string('source')->default('manual');
            $table->boolean('show_on_website')->default(false);
            $table->boolean('featured')->default(false);
            $table->string('slug')->nullable()->unique();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('trim')->nullable();
            $table->string('local_hold')->default('none');
            $table->string('website_status')->default('hidden');
            $table->timestamps();
        });

        Schema::create('saved_vehicles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('vehicle_id');
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->string('type')->default('general');
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('new');
            $table->timestamp('accepted_terms_at')->nullable();
            $table->date('created_on')->nullable();
            $table->timestamps();
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->unsignedBigInteger('dealer_id')->nullable();
            $table->string('type')->nullable();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        \DB::table('integrations')->insert([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
