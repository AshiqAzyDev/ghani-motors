<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\StaffUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DealerInvoiceShowTest extends TestCase
{
    #[Test]
    public function guests_are_redirected_to_dealer_login(): void
    {
        $this->installMinimalInvoiceSchema();
        [, $invoice] = $this->seedInvoiceForDealer();

        $this->get(route('dealer.invoices.show', $invoice))
            ->assertRedirect(route('dealer.login'));
    }

    #[Test]
    public function staff_can_view_an_invoice_for_their_dealer(): void
    {
        $this->installMinimalInvoiceSchema();

        [$staff, $invoice] = $this->seedInvoiceForDealer();

        $this->actingAs($staff, 'staff')
            ->get(route('dealer.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('INV-1006')
            ->assertSee('Alex Buyer')
            ->assertSee('Volkswagen Golf');
    }

    #[Test]
    public function staff_receive_404_for_another_dealers_invoice(): void
    {
        $this->installMinimalInvoiceSchema();

        [$ownStaff] = $this->seedInvoiceForDealer();

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

        $foreignInvoice = Invoice::query()->create([
            'number' => 'INV-OTHER',
            'kind' => 'invoice',
            'customer_id' => $otherCustomer->id,
            'customer_snapshot' => [
                'name' => 'Other Customer',
                'email' => 'other@example.com',
                'phone' => '07000000099',
            ],
            'vehicle_snapshot' => ['label' => 'Other Car'],
            'vat_scheme' => 'margin',
            'prices_include_vat' => true,
            'net_pence' => 100000,
            'vat_pence' => 0,
            'total_pence' => 100000,
            'vat_rate_bps' => 0,
            'status' => 'issued',
            'created_by' => 1,
        ]);

        $this->actingAs($ownStaff, 'staff')
            ->get(route('dealer.invoices.show', $foreignInvoice))
            ->assertNotFound();
    }

    /**
     * @return array{0: StaffUser, 1: Invoice}
     */
    private function seedInvoiceForDealer(): array
    {
        $dealer = Dealer::query()->create([
            'integration_id' => 1,
            'name' => 'Ghani Motors',
            'website_host' => 'localhost',
            'address_line' => 'Rainham Road South',
            'city' => 'Dagenham',
            'postcode' => 'RM10 8YT',
            'phone' => '02000000000',
            'email' => 'accounts@example.com',
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
            'name' => 'Alex Buyer',
            'email' => 'alex.buyer@example.com',
            'phone' => '07700900456',
            'address' => '45 Barking Road',
            'postcode' => 'IG11 8BB',
            'source' => 'website_enquiry',
        ]);

        $invoice = Invoice::query()->create([
            'number' => 'INV-1006',
            'kind' => 'invoice',
            'customer_id' => $customer->id,
            'customer_snapshot' => [
                'name' => 'Alex Buyer',
                'email' => 'alex.buyer@example.com',
                'phone' => '07700900456',
                'address' => '45 Barking Road',
                'postcode' => 'IG11 8BB',
            ],
            'vehicle_snapshot' => [
                'label' => '2017 Volkswagen Golf',
                'registration' => 'VW17GMP',
            ],
            'vat_scheme' => 'margin',
            'prices_include_vat' => true,
            'net_pence' => 1299500,
            'vat_pence' => 0,
            'total_pence' => 1299500,
            'vat_rate_bps' => 0,
            'status' => 'issued',
            'issued_at' => now(),
            'created_by' => $staff->id,
        ]);

        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'sort_order' => 0,
            'line_type' => 'vehicle',
            'description' => '2017 Volkswagen Golf',
            'quantity' => 1,
            'unit_net_pence' => 1299500,
            'vat_rate_bps' => 0,
            'vat_pence' => 0,
            'line_total_pence' => 1299500,
        ]);

        return [$staff, $invoice->fresh(['lines', 'payments'])];
    }

    private function installMinimalInvoiceSchema(): void
    {
        foreach ([
            'invoice_payments', 'invoice_lines', 'invoices', 'customers', 'staff_users', 'dealers', 'integrations',
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
            $table->string('trading_name')->nullable();
            $table->string('website_host')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->string('postcode')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
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

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->string('number');
            $table->string('kind')->default('invoice');
            $table->unsignedBigInteger('related_invoice_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->json('customer_snapshot');
            $table->json('vehicle_snapshot');
            $table->string('vat_scheme');
            $table->boolean('prices_include_vat')->default(true);
            $table->integer('net_pence')->default(0);
            $table->integer('vat_pence')->default(0);
            $table->integer('total_pence')->default(0);
            $table->unsignedInteger('vat_rate_bps')->default(0);
            $table->string('status')->default('draft');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('line_type');
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->integer('unit_net_pence');
            $table->unsignedInteger('vat_rate_bps')->default(0);
            $table->integer('vat_pence')->default(0);
            $table->integer('line_total_pence');
        });

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->integer('amount_pence');
            $table->date('received_on');
            $table->string('method');
            $table->string('reference');
            $table->unsignedBigInteger('recorded_by');
            $table->timestamp('created_at')->nullable();
        });

        \DB::table('integrations')->insert([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
