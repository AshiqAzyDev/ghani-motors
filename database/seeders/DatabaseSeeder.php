<?php

namespace Database\Seeders;

use App\Models\Dealer;
use App\Models\Integration;
use App\Models\InvoiceCounter;
use Illuminate\Database\Seeder;

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
                'email' => 'hello@ghanimotors.co.uk',
                'address_line' => 'Rainham Road South',
                'city' => 'Dagenham',
                'postcode' => 'RM10 8YT',
                'tagline' => 'QUALITY USED CARS',
                'opening_hours' => 'Mon–Sat 9am–6pm, Sun 10am–4pm',
                'on_integration' => true,
            ],
        );

        InvoiceCounter::query()->firstOrCreate(
            ['dealer_id' => $dealer->id],
            ['next_number' => 1001],
        );

        $this->call(DemoDataSeeder::class);
    }
}
