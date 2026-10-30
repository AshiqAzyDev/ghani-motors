<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSetupDealerRequest;
use App\Models\Dealer;
use App\Models\Integration;
use App\Models\InvoiceCounter;
use App\Models\StaffUser;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if ($this->dealerAlreadyConfigured()) {
            return redirect()->route('home');
        }

        return view('setup.dealer', [
            'defaultHost' => request()->getHost(),
        ]);
    }

    public function store(StoreSetupDealerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $created = false;

        try {
            // Serialize first-time setup so concurrent posts cannot both insert a dealer.
            Cache::lock('dealership-setup', 15)->block(10, function () use ($data, &$created): void {
                if ($this->dealerAlreadyConfigured()) {
                    return;
                }

                DB::transaction(function () use ($data): void {
                    $environment = config('autotrader.environment') === 'production' ? 'production' : 'sandbox';

                    $integration = Integration::query()->create([
                        'name' => 'Cloudtechnologies',
                        'environment' => $environment,
                    ]);

                    $dealer = Dealer::query()->create([
                        'integration_id' => $integration->id,
                        'name' => $data['name'],
                        'trading_name' => $data['trading_name'] ?? $data['name'],
                        'phone' => $data['phone'] ?? null,
                        'email' => $data['email'] ?? null,
                        'address_line' => $data['address_line'] ?? null,
                        'city' => $data['city'] ?? null,
                        'postcode' => $data['postcode'] ?? null,
                        'tagline' => $data['tagline'] ?? 'QUALITY USED CARS',
                        'opening_hours' => $data['opening_hours'] ?? null,
                        'website_host' => $data['website_host'],
                        'advertiser_id' => config('autotrader.advertiser_id') ?: null,
                        'on_integration' => true,
                    ]);

                    InvoiceCounter::query()->create([
                        'dealer_id' => $dealer->id,
                        'next_number' => 1001,
                    ]);

                    StaffUser::query()->create([
                        'dealer_id' => $dealer->id,
                        'name' => $data['admin_name'],
                        'email' => $data['admin_email'],
                        'password_hash' => Hash::make($data['admin_password']),
                        'role' => 'admin',
                        'permissions' => null,
                        'is_active' => true,
                    ]);
                });

                $created = true;
            });
        } catch (LockTimeoutException) {
            return redirect()->route('home');
        }

        if (! $created) {
            return redirect()->route('home');
        }

        return redirect()
            ->route('home')
            ->with('status', 'Dealership set up. You can sign in to the dealer workspace when you are ready.');
    }

    private function dealerAlreadyConfigured(): bool
    {
        try {
            return Schema::hasTable('dealers') && Dealer::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
