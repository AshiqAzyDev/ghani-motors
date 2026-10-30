<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Lead;
use App\Models\StaffUser;
use App\Models\Vehicle;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormValidationHardeningTest extends TestCase
{
    #[Test]
    public function dealer_viewing_booking_requires_a_preferred_time(): void
    {
        $this->installDealerOpsSchema();
        [$staff, $vehicle, $customer] = $this->seedOpsDealer();

        $this->actingAs($staff, 'staff')
            ->from(route('dealer.bookings'))
            ->post(route('dealer.bookings.store'), [
                'vehicle_id' => $vehicle->id,
                'customer_id' => $customer->id,
                'type' => 'viewing',
                'preferred_on' => now()->addDay()->toDateString(),
                'notes' => 'Morning preferred',
            ])
            ->assertSessionHasErrors('preferred_time');
    }

    #[Test]
    public function lead_contacted_status_requires_contact_notes_when_empty(): void
    {
        $this->installDealerOpsSchema();
        [$staff, , $customer] = $this->seedOpsDealer();

        $lead = Lead::query()->create([
            'customer_id' => $customer->id,
            'type' => 'general',
            'subject' => 'Website enquiry',
            'status' => 'new',
            'created_on' => now()->toDateString(),
        ]);

        $this->actingAs($staff, 'staff')
            ->from(route('dealer.leads.show', $lead))
            ->post(route('dealer.leads.update', $lead), [
                'status' => 'contacted',
                'contact_notes' => '',
                'additional_notes' => '',
            ])
            ->assertSessionHasErrors('contact_notes');
    }

    #[Test]
    public function finance_enquiry_rejects_invalid_car_slug(): void
    {
        $this->installPublicSiteSchema();
        $this->seedPublicDealer();

        $this->from(route('finance'))
            ->post(route('finance.store'), [
                'car' => 'bad slug!!',
                'name' => 'Alex Customer',
                'email' => 'alex@example.com',
                'phone' => '07000000001',
                'message' => 'Need finance options',
                'accepted_terms' => '1',
            ])
            ->assertSessionHasErrors('car');
    }

    #[Test]
    public function invoice_store_requires_line_description_and_amount(): void
    {
        $this->installDealerOpsSchema();
        [$staff, , $customer] = $this->seedOpsDealer();

        $this->actingAs($staff, 'staff')
            ->from(route('dealer.invoices.create'))
            ->post(route('dealer.invoices.store'), [
                'customer_id' => $customer->id,
                'vat_scheme' => 'margin',
                'prices_include_vat' => '0',
                'lines' => [
                    [
                        'line_type' => 'vehicle',
                        'description' => '',
                        'quantity' => '1',
                        'amount' => '',
                    ],
                ],
            ])
            ->assertSessionHasErrors(['lines.0.description', 'lines.0.amount']);
    }

    #[Test]
    public function register_rejects_email_that_already_has_an_account(): void
    {
        $this->installPublicSiteSchema();
        $dealer = $this->seedPublicDealer();

        Customer::query()->create([
            'dealer_id' => $dealer->id,
            'name' => 'Existing Customer',
            'email' => 'taken@example.com',
            'phone' => '07000000002',
            'password_hash' => 'Password1!',
            'source' => 'website_enquiry',
        ]);

        $this->from(route('register'))
            ->post('/register', [
                'name' => 'New Person',
                'email' => 'taken@example.com',
                'phone' => '07000000003',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
                'accepted_terms' => '1',
            ])
            ->assertSessionHasErrors('email');
    }

    #[Test]
    public function register_upgrades_lead_only_customer_without_password(): void
    {
        $this->installPublicSiteSchema();
        Mail::fake();
        $dealer = $this->seedPublicDealer();

        $leadOnly = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'name' => 'Lead Only',
            'email' => 'lead@example.com',
            'phone' => '07000000004',
            'password_hash' => null,
            'source' => 'website_enquiry',
        ]);

        $this->from(route('register'))
            ->post('/register', [
                'name' => 'Lead Upgraded',
                'email' => 'lead@example.com',
                'phone' => '07000000005',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
                'accepted_terms' => '1',
            ])
            ->assertRedirect(route('account'));

        $leadOnly->refresh();
        $this->assertSame('Lead Upgraded', $leadOnly->name);
        $this->assertNotNull($leadOnly->password_hash);
        $this->assertAuthenticatedAs($leadOnly);
    }

    /**
     * @return array{0: StaffUser, 1: Vehicle, 2: Customer}
     */
    private function seedOpsDealer(): array
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
            'password_hash' => 'Password1!',
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
            'website_status' => 'available',
            'local_hold' => 'none',
        ]);

        return [$staff, $vehicle, $customer];
    }

    private function seedPublicDealer(): Dealer
    {
        return Dealer::query()->create([
            'integration_id' => 1,
            'name' => 'Ghani Motors',
            'website_host' => 'localhost',
            'phone' => '020 0000 0000',
        ]);
    }

    private function installPublicSiteSchema(): void
    {
        foreach (['leads', 'customers', 'vehicles', 'dealers', 'integrations'] as $table) {
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

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dealer_id');
            $table->string('environment')->default('sandbox');
            $table->string('source')->default('manual');
            $table->boolean('show_on_website')->default(true);
            $table->string('slug')->nullable()->unique();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('website_status')->default('available');
            $table->string('local_hold')->default('none');
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dealer_id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('password_hash')->nullable();
            $table->string('source')->default('website_enquiry');
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

        \DB::table('integrations')->insert([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function installDealerOpsSchema(): void
    {
        foreach ([
            'invoice_lines', 'invoices', 'invoice_counters', 'activity_logs', 'app_notifications',
            'leads', 'bookings', 'vehicles', 'customers', 'staff_users', 'dealers', 'integrations',
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
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
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
            $table->string('source')->default('website_enquiry');
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dealer_id');
            $table->string('environment')->default('sandbox');
            $table->string('source')->default('manual');
            $table->boolean('show_on_website')->default(true);
            $table->boolean('featured')->default(false);
            $table->string('slug')->nullable()->unique();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('website_status')->default('available');
            $table->string('local_hold')->default('none');
            $table->timestamps();
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
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('held_reservation_key')->nullable();
            $table->string('active_test_drive_key')->nullable();
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
            $table->text('contact_notes')->nullable();
            $table->text('additional_notes')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('accepted_terms_at')->nullable();
            $table->date('created_on')->nullable();
            $table->json('requirements')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dealer_id')->unique();
            $table->unsignedInteger('next_number')->default(1001);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dealer_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->string('number');
            $table->string('status')->default('draft');
            $table->string('vat_scheme')->default('margin');
            $table->boolean('prices_include_vat')->default(false);
            $table->string('vehicle_label')->nullable();
            $table->integer('net_pence')->default(0);
            $table->integer('vat_pence')->default(0);
            $table->integer('total_pence')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->string('line_type')->nullable();
            $table->string('description')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->integer('amount_pence')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_user_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->string('message');
            $table->timestamps();
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->string('type')->nullable();
            $table->string('title')->nullable();
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

        \DB::table('invoice_counters')->insert([
            'dealer_id' => 1,
            'next_number' => 1001,
        ]);
    }
}
