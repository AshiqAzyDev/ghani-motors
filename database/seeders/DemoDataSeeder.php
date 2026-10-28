<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Invoice;
use App\Models\InvoiceCounter;
use App\Models\InvoiceLine;
use App\Models\InvoicePayment;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\SavedVehicle;
use App\Models\StaffUser;
use App\Models\Valuation;
use App\Models\Vehicle;
use App\Services\VehicleStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $dealer = Dealer::query()->orderBy('id')->first();
        if (! $dealer) {
            $this->command?->error('No dealer found. Run DatabaseSeeder first.');

            return;
        }

        $password = (string) env('DEMO_PASSWORD', 'password');
        $adminEmail = strtolower((string) env('STAFF_ADMIN_EMAIL', 'admin@ghanimotors.co.uk'));
        $adminPassword = (string) env('STAFF_ADMIN_PASSWORD', '') ?: $password;
        $environment = config('autotrader.environment') === 'production' ? 'production' : 'sandbox';
        $status = app(VehicleStatus::class);

        $admin = $this->seedStaff($dealer, [
            ['name' => 'Ghani Motors admin', 'email' => $adminEmail, 'role' => 'admin', 'password' => $adminPassword],
            ['name' => 'Demo Sales', 'email' => 'sales@ghanimotors.co.uk', 'role' => 'sales', 'password' => $password],
            ['name' => 'Demo Viewer', 'email' => 'viewer@ghanimotors.co.uk', 'role' => 'viewer', 'password' => $password],
        ]);

        $vehicles = $this->seedVehicles($dealer, $environment, $status, $admin);
        $customers = $this->seedCustomers($dealer, $password);
        $this->seedLeadsAndValuations($customers, $vehicles, $admin);
        $this->seedBookings($customers, $vehicles, $admin);
        $this->seedSavedVehicles($customers, $vehicles);
        $this->seedSaleAndInvoice($dealer, $customers, $vehicles, $admin, $status);
        $this->seedNotifications($dealer, $admin, $customers);
        $this->seedActivity($admin, $customers, $vehicles);

        $this->command?->newLine();
        $this->command?->info('Demo logins');
        $this->command?->table(
            ['Type', 'Email', 'Password', 'Role'],
            [
                ['Staff', $adminEmail, $adminPassword, 'admin'],
                ['Staff', 'sales@ghanimotors.co.uk', $password, 'sales'],
                ['Staff', 'viewer@ghanimotors.co.uk', $password, 'viewer'],
                ['Customer', 'demo@example.com', $password, 'registered'],
                ['Customer', 'alex.buyer@example.com', $password, 'registered'],
                ['Customer', 'sam.px@example.com', $password, 'registered'],
                ['Customer', 'jordan.enquiry@example.com', '(none)', 'guest lead only'],
            ],
        );
    }

    /**
     * @param  list<array{name: string, email: string, role: string, password: string}>  $people
     */
    private function seedStaff(Dealer $dealer, array $people): StaffUser
    {
        $admin = null;

        foreach ($people as $person) {
            $staff = StaffUser::query()->firstOrNew(['email' => $person['email']]);
            $staff->dealer_id = $dealer->id;
            $staff->name = $person['name'];
            $staff->role = $person['role'];
            $staff->permissions = null;
            $staff->is_active = true;
            if (! $staff->exists || blank($staff->password_hash)) {
                $staff->password_hash = $person['password'];
            }
            $staff->save();

            if ($person['role'] === 'admin') {
                $admin = $staff;
            }
        }

        return $admin ?? StaffUser::query()->where('dealer_id', $dealer->id)->where('role', 'admin')->firstOrFail();
    }

    /**
     * @return array<string, Vehicle>
     */
    private function seedVehicles(Dealer $dealer, string $environment, VehicleStatus $status, StaffUser $admin): array
    {
        $samples = [
            'bmw' => ['BMW', '3 Series', '320d M Sport', 2019, 41200, 'Diesel', 'Automatic', 'Saloon', 'Black', 1649500, 'BN19GMP', true],
            'audi' => ['Audi', 'A6', 'S line', 2020, 29800, 'Diesel', 'Automatic', 'Saloon', 'Grey', 2145000, 'AU20GMP', true],
            'merc' => ['Mercedes-Benz', 'C Class', 'AMG Line', 2018, 53600, 'Petrol', 'Automatic', 'Saloon', 'White', 1799500, 'ME18GMP', false],
            'volvo' => ['Volvo', 'XC90', 'Momentum', 2019, 62100, 'Diesel', 'Automatic', 'SUV', 'Blue', 2499500, 'VO19GMP', false],
            'ford' => ['Ford', 'Focus', 'ST-Line', 2021, 18400, 'Petrol', 'Manual', 'Hatchback', 'Red', 1499500, 'FO21GMP', false],
            'sold' => ['Volkswagen', 'Golf', 'GTI', 2017, 67800, 'Petrol', 'Manual', 'Hatchback', 'White', 1299500, 'VW17GMP', false],
        ];

        $keyed = [];

        foreach ($samples as $key => [$make, $model, $trim, $year, $mileage, $fuel, $transmission, $body, $colour, $price, $reg, $featured]) {
            $vehicle = Vehicle::query()->firstOrNew([
                'dealer_id' => $dealer->id,
                'at_stock_id' => 'sample-'.$reg,
            ]);

            $vehicle->fill([
                'environment' => $environment,
                'source' => 'autotrader',
                'registration' => $reg,
                'make' => $make,
                'model' => $model,
                'trim' => $trim,
                'year' => $year,
                'mileage' => $mileage,
                'fuel' => $fuel,
                'transmission' => $transmission,
                'body' => $body,
                'colour' => $colour,
                'doors' => 5,
                'seats' => 5,
                'owners' => 2,
                'cash_price_pence' => $price,
                'supplied_price_pence' => $price,
                'total_price_pence' => $price,
                'advertiser_advert_status' => 'PUBLISHED',
                'at_lifecycle' => 'FORECOURT',
                'featured' => $featured,
                'attention_grabber' => 'Prepared and ready to view',
                'description' => 'Sample stock for local demos and UI review. Hidden automatically if a live Autotrader baseline does not return this stock id.',
                'local_hold' => 'none',
                'internal_notes' => 'Demo sample car.',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

            if ($key === 'sold' && ! $vehicle->exists) {
                // Sale seeder marks this sold; keep forecourt until then.
            }

            $status->apply($vehicle);
            $vehicle->save();

            if (! filled($vehicle->slug)) {
                $vehicle->slug = Str::slug($year.' '.$make.' '.$model).'-'.$vehicle->id;
                $vehicle->save();
            }

            $keyed[$key] = $vehicle;
        }

        return $keyed;
    }

    /**
     * @return array<string, Customer>
     */
    private function seedCustomers(Dealer $dealer, string $password): array
    {
        $rows = [
            'demo' => [
                'name' => 'Demo Customer',
                'email' => 'demo@example.com',
                'phone' => '07700 900123',
                'password_hash' => $password,
                'address' => '12 High Street',
                'postcode' => 'RM10 8AA',
                'notes' => 'Primary demo account for the customer portal.',
                'source' => 'website_enquiry',
            ],
            'alex' => [
                'name' => 'Alex Buyer',
                'email' => 'alex.buyer@example.com',
                'phone' => '07700 900456',
                'password_hash' => $password,
                'address' => '45 Barking Road',
                'postcode' => 'IG11 8BB',
                'notes' => 'Interested in executive saloons.',
                'source' => 'finance_enquiry',
            ],
            'jordan' => [
                'name' => 'Jordan Enquiry',
                'email' => 'jordan.enquiry@example.com',
                'phone' => '07700 900789',
                'password_hash' => null,
                'address' => null,
                'postcode' => 'E6 2CC',
                'notes' => 'Website guest lead only — no portal login.',
                'source' => 'website_enquiry',
            ],
            'sam' => [
                'name' => 'Sam Part-Exchange',
                'email' => 'sam.px@example.com',
                'phone' => '07700 900321',
                'password_hash' => $password,
                'address' => '8 Rainham Lane',
                'postcode' => 'RM13 9DD',
                'notes' => 'Looking to part-exchange a hatchback.',
                'source' => 'sell_your_car',
            ],
        ];

        $customers = [];

        foreach ($rows as $key => $data) {
            $customer = Customer::query()->firstOrNew(['email' => $data['email']]);
            $customer->dealer_id = $dealer->id;
            $customer->name = $data['name'];
            $customer->phone = $data['phone'];
            $customer->address = $data['address'];
            $customer->postcode = $data['postcode'];
            $customer->notes = $data['notes'];
            $customer->source = $data['source'];
            if ($data['password_hash'] !== null && (! $customer->exists || blank($customer->password_hash))) {
                $customer->password_hash = $data['password_hash'];
            }
            $customer->save();
            $customers[$key] = $customer;
        }

        return $customers;
    }

    /**
     * @param  array<string, Customer>  $customers
     * @param  array<string, Vehicle>  $vehicles
     */
    private function seedLeadsAndValuations(array $customers, array $vehicles, StaffUser $admin): void
    {
        $sales = StaffUser::query()->where('email', 'sales@ghanimotors.co.uk')->first() ?? $admin;

        $leads = [
            [
                'customer' => $customers['demo'],
                'vehicle' => $vehicles['bmw'],
                'assigned_to' => $sales->id,
                'type' => 'viewing',
                'subject' => 'Demo: viewing request for BMW 3 Series',
                'message' => 'Please can I book a viewing this weekend?',
                'status' => 'contacted',
                'contact_notes' => 'Called back — happy with Saturday morning.',
                'additional_notes' => 'Prefers diesel automatics.',
                'requirements' => ['budget' => 'Up to £17,000', 'fuel' => 'Diesel', 'transmission' => 'Automatic'],
                'contacted_at' => now()->subDays(1),
                'created_on' => now()->subDays(2)->toDateString(),
            ],
            [
                'customer' => $customers['alex'],
                'vehicle' => $vehicles['audi'],
                'assigned_to' => $sales->id,
                'type' => 'finance',
                'subject' => 'Demo: finance enquiry for Audi A6',
                'message' => 'Looking at PCP options with a £3,000 deposit.',
                'status' => 'new',
                'contact_notes' => null,
                'additional_notes' => null,
                'requirements' => ['budget' => '£350/month', 'mileage' => 'Under 40,000'],
                'contacted_at' => null,
                'created_on' => now()->subHours(8)->toDateString(),
            ],
            [
                'customer' => $customers['jordan'],
                'vehicle' => $vehicles['merc'],
                'assigned_to' => null,
                'type' => 'general',
                'subject' => 'Demo: general enquiry about Mercedes C Class',
                'message' => 'Is the service history full? Any pending advisories?',
                'status' => 'new',
                'contact_notes' => null,
                'additional_notes' => null,
                'requirements' => null,
                'contacted_at' => null,
                'created_on' => now()->subDay()->toDateString(),
            ],
            [
                'customer' => $customers['sam'],
                'vehicle' => $vehicles['ford'],
                'assigned_to' => $admin->id,
                'type' => 'part_exchange',
                'subject' => 'Demo: part-exchange against Ford Focus',
                'message' => 'I have a 2015 Fiesta to put towards the Focus.',
                'status' => 'contacted',
                'contact_notes' => 'Asked for valuation photos.',
                'additional_notes' => 'Happy to visit the showroom mid-week.',
                'requirements' => ['budget' => '£15,000', 'body' => 'Hatchback'],
                'contacted_at' => now()->subDays(3),
                'created_on' => now()->subDays(4)->toDateString(),
            ],
            [
                'customer' => $customers['sam'],
                'vehicle' => null,
                'assigned_to' => $sales->id,
                'type' => 'sell',
                'subject' => 'Demo: sell-your-car valuation',
                'message' => 'Looking for a free valuation on my current car.',
                'status' => 'closed',
                'contact_notes' => 'Valuation completed and offer sent.',
                'additional_notes' => null,
                'requirements' => null,
                'contacted_at' => now()->subDays(5),
                'created_on' => now()->subDays(6)->toDateString(),
            ],
        ];

        foreach ($leads as $row) {
            $lead = Lead::query()->firstOrCreate(
                [
                    'customer_id' => $row['customer']->id,
                    'subject' => $row['subject'],
                ],
                [
                    'vehicle_id' => $row['vehicle']?->id,
                    'assigned_to' => $row['assigned_to'],
                    'type' => $row['type'],
                    'message' => $row['message'],
                    'status' => $row['status'],
                    'contact_notes' => $row['contact_notes'],
                    'additional_notes' => $row['additional_notes'],
                    'requirements' => $row['requirements'],
                    'contacted_at' => $row['contacted_at'],
                    'accepted_terms_at' => now()->subDays(2),
                    'created_on' => $row['created_on'],
                ],
            );

            if ($row['type'] === 'sell' && ! $lead->valuation()->exists()) {
                Valuation::query()->create([
                    'lead_id' => $lead->id,
                    'registration' => 'AB15 XYZ',
                    'mileage' => 72000,
                    'make' => 'Ford',
                    'model' => 'Fiesta',
                    'year' => 2015,
                    'fuel' => 'Petrol',
                    'transmission' => 'Manual',
                    'intent' => 'sell',
                    'condition' => 'good',
                    'service_history' => 'partial',
                    'mot_expiry' => now()->addMonths(4)->toDateString(),
                    'outstanding_finance' => 'no',
                    'notes' => 'Minor scuff on rear bumper. Demo valuation only.',
                    'preferred_contact' => 'phone',
                    'accepted_terms_at' => now()->subDays(6),
                ]);
            }

            if ($row['type'] === 'part_exchange' && ! $lead->valuation()->exists()) {
                Valuation::query()->create([
                    'lead_id' => $lead->id,
                    'registration' => 'CD15 PQR',
                    'mileage' => 68000,
                    'make' => 'Ford',
                    'model' => 'Fiesta',
                    'year' => 2015,
                    'fuel' => 'Petrol',
                    'transmission' => 'Manual',
                    'intent' => 'part_exchange',
                    'condition' => 'good',
                    'service_history' => 'full',
                    'mot_expiry' => now()->addMonths(8)->toDateString(),
                    'outstanding_finance' => 'no',
                    'notes' => 'Demo part-exchange valuation.',
                    'preferred_contact' => 'email',
                    'accepted_terms_at' => now()->subDays(4),
                ]);
            }
        }
    }

    /**
     * @param  array<string, Customer>  $customers
     * @param  array<string, Vehicle>  $vehicles
     */
    private function seedBookings(array $customers, array $vehicles, StaffUser $admin): void
    {
        $sales = StaffUser::query()->where('email', 'sales@ghanimotors.co.uk')->first() ?? $admin;

        $bookings = [
            [
                'vehicle' => $vehicles['bmw'],
                'customer' => $customers['demo'],
                'type' => 'viewing',
                'status' => 'confirmed',
                'preferred_on' => now()->addDays(2)->toDateString(),
                'preferred_time' => '10:30:00',
                'notes' => 'Demo confirmed viewing.',
            ],
            [
                'vehicle' => $vehicles['audi'],
                'customer' => $customers['alex'],
                'type' => 'test_drive',
                'status' => 'requested',
                'preferred_on' => now()->addDays(3)->toDateString(),
                'preferred_time' => '14:00:00',
                'notes' => 'Demo test-drive request awaiting confirmation.',
            ],
            [
                'vehicle' => $vehicles['volvo'],
                'customer' => $customers['demo'],
                'type' => 'reservation',
                'status' => 'held',
                'preferred_on' => now()->toDateString(),
                'preferred_time' => null,
                'notes' => 'Demo held reservation — £99 soft hold.',
            ],
            [
                'vehicle' => $vehicles['merc'],
                'customer' => $customers['jordan'],
                'type' => 'viewing',
                'status' => 'cancelled',
                'preferred_on' => now()->subDays(1)->toDateString(),
                'preferred_time' => '11:00:00',
                'notes' => 'Customer cancelled — demo history row.',
            ],
        ];

        foreach ($bookings as $row) {
            $exists = Booking::query()
                ->where('vehicle_id', $row['vehicle']->id)
                ->where('customer_id', $row['customer']->id)
                ->where('type', $row['type'])
                ->where('preferred_on', $row['preferred_on'])
                ->exists();

            if ($exists) {
                continue;
            }

            if ($row['type'] === 'reservation' && $row['status'] === 'held') {
                $vehicles['volvo']->local_hold = 'reserved';
                app(VehicleStatus::class)->apply($vehicles['volvo']);
                $vehicles['volvo']->save();
            }

            Booking::query()->create([
                'vehicle_id' => $row['vehicle']->id,
                'customer_id' => $row['customer']->id,
                'type' => $row['type'],
                'status' => $row['status'],
                'preferred_on' => $row['preferred_on'],
                'preferred_time' => $row['preferred_time'],
                'notes' => $row['notes'],
                'created_by' => $sales->id,
                'updated_by' => $sales->id,
            ]);
        }
    }

    /**
     * @param  array<string, Customer>  $customers
     * @param  array<string, Vehicle>  $vehicles
     */
    private function seedSavedVehicles(array $customers, array $vehicles): void
    {
        foreach ([$vehicles['bmw'], $vehicles['audi'], $vehicles['ford']] as $vehicle) {
            SavedVehicle::query()->firstOrCreate([
                'customer_id' => $customers['demo']->id,
                'vehicle_id' => $vehicle->id,
            ]);
        }

        SavedVehicle::query()->firstOrCreate([
            'customer_id' => $customers['alex']->id,
            'vehicle_id' => $vehicles['audi']->id,
        ]);
    }

    /**
     * @param  array<string, Customer>  $customers
     * @param  array<string, Vehicle>  $vehicles
     */
    private function seedSaleAndInvoice(
        Dealer $dealer,
        array $customers,
        array $vehicles,
        StaffUser $admin,
        VehicleStatus $status,
    ): void {
        $vehicle = $vehicles['sold'];

        if (Sale::query()->where('vehicle_id', $vehicle->id)->where('status', 'completed')->exists()) {
            return;
        }

        $sale = Sale::query()->create([
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customers['alex']->id,
            'vehicle_label' => $vehicle->title() ?: 'Volkswagen Golf GTI',
            'registration' => $vehicle->registration,
            'price_pence' => (int) $vehicle->total_price_pence,
            'sold_on' => now()->subDays(10)->toDateString(),
            'method' => 'bank_transfer',
            'status' => 'completed',
            'notes' => 'Demo completed sale for invoices and sales screens.',
            'created_by' => $admin->id,
        ]);

        $vehicle->local_hold = 'none';
        $status->apply($vehicle);
        $vehicle->save();

        $counter = InvoiceCounter::query()->firstOrCreate(
            ['dealer_id' => $dealer->id],
            ['next_number' => 1001],
        );
        $number = (string) $counter->next_number;
        $counter->next_number = $counter->next_number + 1;
        $counter->save();

        $total = (int) $sale->price_pence;
        // Margin scheme: no VAT line amount for demo simplicity.
        $invoice = Invoice::query()->create([
            'sale_id' => $sale->id,
            'number' => $number,
            'kind' => 'invoice',
            'customer_id' => $customers['alex']->id,
            'customer_snapshot' => [
                'name' => $customers['alex']->name,
                'email' => $customers['alex']->email,
                'phone' => $customers['alex']->phone,
                'address' => $customers['alex']->address,
                'postcode' => $customers['alex']->postcode,
            ],
            'vehicle_snapshot' => [
                'label' => $sale->vehicle_label,
                'registration' => $sale->registration,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
            ],
            'vat_scheme' => 'margin',
            'prices_include_vat' => true,
            'net_pence' => $total,
            'vat_pence' => 0,
            'total_pence' => $total,
            'vat_rate_bps' => 0,
            'status' => 'paid',
            'issued_at' => now()->subDays(10),
            'created_by' => $admin->id,
        ]);

        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'sort_order' => 1,
            'line_type' => 'vehicle',
            'description' => $sale->vehicle_label,
            'quantity' => 1,
            'unit_net_pence' => $total,
            'vat_rate_bps' => 0,
            'vat_pence' => 0,
            'line_total_pence' => $total,
        ]);

        InvoicePayment::query()->create([
            'invoice_id' => $invoice->id,
            'amount_pence' => $total,
            'received_on' => now()->subDays(9)->toDateString(),
            'method' => 'bank_transfer',
            'reference' => 'DEMO-PAY-'.$number,
            'recorded_by' => $admin->id,
        ]);
    }

    /**
     * @param  array<string, Customer>  $customers
     */
    private function seedNotifications(Dealer $dealer, StaffUser $admin, array $customers): void
    {
        $sales = StaffUser::query()->where('email', 'sales@ghanimotors.co.uk')->first();

        $staffTargets = array_values(array_filter([$admin, $sales]));

        foreach ($staffTargets as $staff) {
            AppNotification::query()->firstOrCreate(
                [
                    'notifiable_type' => 'staff',
                    'notifiable_id' => $staff->id,
                    'type' => 'lead.new',
                    'title' => 'Demo: new finance enquiry',
                ],
                [
                    'dealer_id' => $dealer->id,
                    'body' => 'Alex Buyer enquired about the Audi A6.',
                    'url' => '/dealer/leads',
                    'read_at' => null,
                ],
            );

            AppNotification::query()->firstOrCreate(
                [
                    'notifiable_type' => 'staff',
                    'notifiable_id' => $staff->id,
                    'type' => 'booking.new',
                    'title' => 'Demo: test drive requested',
                ],
                [
                    'dealer_id' => $dealer->id,
                    'body' => 'A customer requested a test drive.',
                    'url' => '/dealer/bookings',
                    'read_at' => now()->subHour(),
                ],
            );
        }

        AppNotification::query()->firstOrCreate(
            [
                'notifiable_type' => 'customer',
                'notifiable_id' => $customers['demo']->id,
                'type' => 'booking.updated',
                'title' => 'Demo: viewing confirmed',
            ],
            [
                'dealer_id' => $dealer->id,
                'body' => 'Your BMW viewing is confirmed for this weekend.',
                'url' => '/account',
                'read_at' => null,
            ],
        );
    }

    /**
     * @param  array<string, Customer>  $customers
     * @param  array<string, Vehicle>  $vehicles
     */
    private function seedActivity(StaffUser $admin, array $customers, array $vehicles): void
    {
        ActivityLog::query()->firstOrCreate(
            [
                'staff_user_id' => $admin->id,
                'message' => 'Demo: contacted Demo Customer about BMW viewing',
            ],
            [
                'vehicle_id' => $vehicles['bmw']->id,
                'customer_id' => $customers['demo']->id,
            ],
        );

        ActivityLog::query()->firstOrCreate(
            [
                'staff_user_id' => $admin->id,
                'message' => 'Demo: recorded sale for Volkswagen Golf GTI',
            ],
            [
                'vehicle_id' => $vehicles['sold']->id,
                'customer_id' => $customers['alex']->id,
            ],
        );
    }
}
