<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Sale;
use App\Models\StaffUser;
use App\Models\Vehicle;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DealerSaleShowTest extends TestCase
{
    #[Test]
    public function guests_are_redirected_to_dealer_login(): void
    {
        $this->installMinimalDealerSaleSchema();
        [, $sale] = $this->seedSaleForDealer();

        $this->get(route('dealer.sales.show', $sale))
            ->assertRedirect(route('dealer.login'));
    }

    #[Test]
    public function staff_can_view_a_sale_for_their_dealer(): void
    {
        $this->installMinimalDealerSaleSchema();

        [$staff, $sale] = $this->seedSaleForDealer();

        $this->actingAs($staff, 'staff')
            ->get(route('dealer.sales.show', $sale))
            ->assertOk()
            ->assertSee('Alex Customer')
            ->assertSee('Ford Focus')
            ->assertSee('Completed');
    }

    #[Test]
    public function staff_receive_404_for_another_dealers_sale(): void
    {
        $this->installMinimalDealerSaleSchema();

        [$ownStaff] = $this->seedSaleForDealer();

        $otherDealer = Dealer::query()->create([
            'integration_id' => 1,
            'name' => 'Other Motors',
            'website_host' => 'other.test',
        ]);

        $otherCustomer = Customer::query()->create([
            'dealer_id' => $otherDealer->id,
            'name' => 'Other Customer',
            'email' => 'other@example.com',
            'phone' => '07000000099',
            'source' => 'showroom',
        ]);

        $otherVehicle = Vehicle::query()->create([
            'dealer_id' => $otherDealer->id,
            'environment' => 'sandbox',
            'source' => 'manual',
            'show_on_website' => true,
            'slug' => 'other-car',
            'make' => 'BMW',
            'model' => '3 Series',
            'website_status' => 'sold',
            'local_hold' => 'none',
        ]);

        $foreignSale = Sale::query()->create([
            'vehicle_id' => $otherVehicle->id,
            'customer_id' => $otherCustomer->id,
            'vehicle_label' => 'BMW 3 Series',
            'price_pence' => 1500000,
            'sold_on' => now()->toDateString(),
            'method' => 'cash',
            'status' => 'completed',
            'created_by' => 1,
        ]);

        $this->actingAs($ownStaff, 'staff')
            ->get(route('dealer.sales.show', $foreignSale))
            ->assertNotFound();
    }

    /**
     * @return array{0: StaffUser, 1: Sale}
     */
    private function seedSaleForDealer(): array
    {
        $dealer = Dealer::query()->create([
            'integration_id' => 1,
            'name' => 'Ghani Motors',
            'website_host' => 'localhost',
        ]);

        $staff = StaffUser::query()->create([
            'dealer_id' => $dealer->id,
            'name' => 'Sam Staff',
            'email' => 'sam@example.com',
            'password_hash' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'name' => 'Alex Customer',
            'email' => 'alex@example.com',
            'phone' => '07000000001',
            'source' => 'website_enquiry',
        ]);

        $vehicle = Vehicle::query()->create([
            'dealer_id' => $dealer->id,
            'environment' => 'sandbox',
            'source' => 'manual',
            'show_on_website' => true,
            'slug' => 'demo-car',
            'make' => 'Ford',
            'model' => 'Focus',
            'website_status' => 'sold',
            'local_hold' => 'none',
        ]);

        $sale = Sale::query()->create([
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customer->id,
            'vehicle_label' => 'Ford Focus',
            'price_pence' => 999500,
            'sold_on' => now()->toDateString(),
            'method' => 'bank_transfer',
            'status' => 'completed',
            'created_by' => $staff->id,
            'notes' => 'Collected today',
        ]);

        return [$staff, $sale];
    }

    private function installMinimalDealerSaleSchema(): void
    {
        foreach ([
            'invoices', 'sales', 'leads', 'bookings', 'vehicle_images', 'vehicles', 'customers', 'staff_users', 'dealers', 'integrations',
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
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->json('website_settings')->nullable();
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

        Schema::create('vehicle_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_id');
            $table->string('url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('type');
            $table->string('status');
            $table->date('preferred_on');
            $table->time('preferred_time')->nullable();
            $table->text('notes')->nullable();
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
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('part_exchange_valuation_id')->nullable();
            $table->string('vehicle_label');
            $table->string('registration')->nullable();
            $table->integer('price_pence');
            $table->date('sold_on');
            $table->string('method')->default('cash');
            $table->string('status')->default('completed');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->default(1);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->string('number');
            $table->string('status')->default('draft');
            $table->integer('total_pence')->default(0);
            $table->timestamps();
        });

        \DB::table('integrations')->insert([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
