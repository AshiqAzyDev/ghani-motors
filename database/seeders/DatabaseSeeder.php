<?php

namespace Database\Seeders;

use App\Models\Dealer;
use App\Models\Integration;
use App\Models\InvoiceCounter;
use App\Models\StaffUser;
use App\Models\Vehicle;
use App\Services\VehicleStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $environment = config('autotrader.environment') === 'production' ? 'production' : 'sandbox';

        $integration = Integration::query()->updateOrCreate(
            ['name' => 'Cloudtechnologies'],
            ['environment' => $environment],
        );

        $advertiser = config('autotrader.advertiser_id') ?: null;
        $dealer = Dealer::query()->updateOrCreate(
            ['name' => 'Ghani Motors Ltd'],
            [
                'integration_id' => $integration->id,
                'trading_name' => 'Ghani Motors',
                'advertiser_id' => $advertiser,
                'phone' => '020 0000 0000',
                'address_line' => 'Rainham Road South',
                'city' => 'Dagenham',
                'postcode' => 'RM10 8YT',
                'on_integration' => true,
            ],
        );

        InvoiceCounter::query()->firstOrCreate(
            ['dealer_id' => $dealer->id],
            ['next_number' => 1001],
        );

        $email = strtolower((string) env('STAFF_ADMIN_EMAIL', 'admin@ghanimotors.co.uk'));
        $password = (string) env('STAFF_ADMIN_PASSWORD', '');
        if ($password === '') {
            $password = 'ChangeMe-'.Str::lower(Str::random(8));
            $this->command?->warn('STAFF_ADMIN_PASSWORD was empty. A temporary password was generated.');
        }

        $staff = StaffUser::query()->firstOrNew(['email' => $email]);
        $staff->dealer_id = $dealer->id;
        $staff->name = 'Ghani Motors admin';
        $staff->role = 'admin';
        $staff->is_active = true;
        if (! $staff->exists) {
            $staff->password_hash = $password;
        }
        $staff->save();
        $this->command?->info('Staff login: '.$email);

        if (Vehicle::query()->where('dealer_id', $dealer->id)->exists()) {
            return;
        }

        $status = app(VehicleStatus::class);
        $samples = [
            ['BMW', '3 Series', '320d M Sport', 2019, 41200, 'Diesel', 'Automatic', 'Saloon', 'Black', 1649500, 'BN19GMP'],
            ['Audi', 'A6', 'S line', 2020, 29800, 'Diesel', 'Automatic', 'Saloon', 'Grey', 2145000, 'AU20GMP'],
            ['Mercedes-Benz', 'C Class', 'AMG Line', 2018, 53600, 'Petrol', 'Automatic', 'Saloon', 'White', 1799500, 'ME18GMP'],
            ['Volvo', 'XC90', 'Momentum', 2019, 62100, 'Diesel', 'Automatic', 'SUV', 'Blue', 2499500, 'VO19GMP'],
        ];

        foreach ($samples as $index => [$make, $model, $trim, $year, $mileage, $fuel, $transmission, $body, $colour, $price, $reg]) {
            $vehicle = new Vehicle([
                'dealer_id' => $dealer->id,
                'environment' => $environment,
                'source' => 'autotrader',
                'at_stock_id' => 'sample-'.$reg,
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
                'attention_grabber' => 'Prepared and ready to view',
                'description' => 'Sample stock so the site can be reviewed before the first Autotrader baseline. A successful baseline hides any sample car that is not in the live stock feed.',
                'local_hold' => 'none',
                'internal_notes' => 'Sample car. Hidden automatically if a baseline does not return this stock id.',
            ]);
            $status->apply($vehicle);
            $vehicle->save();
            $vehicle->slug = Str::slug($year.' '.$make.' '.$model).'-'.$vehicle->id;
            $vehicle->save();
        }
    }
}
