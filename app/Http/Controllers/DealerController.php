<?php

namespace App\Http\Controllers;

use App\Exceptions\AutotraderException;
use App\Models\ActivityLog;
use App\Models\ApiBlock;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\SavedVehicle;
use App\Models\StaffUser;
use App\Models\SyncRun;
use App\Models\Valuation;
use App\Models\Vehicle;
use App\Models\VehiclePayload;
use App\Models\VehiclePriceHistory;
use App\Models\WebhookEvent;
use App\Services\AppNotifier;
use App\Services\Autotrader\StockSync;
use App\Services\BookingService;
use App\Services\CustomerDirectory;
use App\Services\DatabaseMigrationService;
use App\Services\InvoiceService;
use App\Services\Mail\GhaniMailer;
use App\Services\VehicleStatus;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class DealerController extends Controller
{
    public function __construct(
        private VehicleStatus $status,
        private CustomerDirectory $directory,
        private BookingService $bookings,
        private InvoiceService $invoices,
        private StockSync $sync,
        private GhaniMailer $mailer,
        private AppNotifier $notifier,
        private DatabaseMigrationService $migrations,
    ) {}

    public function showLogin()
    {
        return view('dealer.login');
    }

    public function showForgotPassword()
    {
        return view('dealer.forgot');
    }

    public function sendResetLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $staff = StaffUser::query()->where('email', strtolower($data['email']))->first();
        if ($staff) {
            Password::broker('staff')->sendResetLink(['email' => $staff->email]);
        }

        return view('dealer.forgot', ['sent' => true]);
    }

    public function showResetPassword(Request $request, string $token)
    {
        return view('dealer.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker('staff')->reset(
            [
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'password_confirmation' => $data['password'],
                'token' => $data['token'],
            ],
            function (StaffUser $staff, string $password): void {
                $staff->password_hash = $password;
                $staff->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('dealer.login')->with('status', 'Password updated. You can log in.');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        if (! Auth::guard('staff')->attempt(['email' => strtolower($data['email']), 'password' => $data['password']])) {
            Log::warning('Staff login failed', [
                'email' => strtolower($data['email']),
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages(['email' => 'Those staff details were not recognised.']);
        }
        if (! $request->user('staff')->is_active) {
            Auth::guard('staff')->logout();
            throw ValidationException::withMessages(['email' => 'This staff account is not active.']);
        }
        $request->session()->regenerate();
        $staff = $request->user('staff');
        $staff->last_login_at = now();
        $staff->save();

        return redirect()->route('dealer.home');
    }

    public function logout(Request $request)
    {
        Auth::guard('staff')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('dealer.login');
    }

    public function home(Request $request)
    {
        $dealer = $request->user('staff')->dealer;
        $dealerId = $dealer->id;
        $monthStart = now()->startOfMonth()->toDateString();

        $counts = Vehicle::query()->where('dealer_id', $dealerId)
            ->selectRaw('website_status, COUNT(*) as total')
            ->groupBy('website_status')
            ->pluck('total', 'website_status');

        $stockValue = Vehicle::query()
            ->where('dealer_id', $dealerId)
            ->whereIn('website_status', ['available', 'reserved', 'preparation'])
            ->get()
            ->sum(fn (Vehicle $vehicle) => $vehicle->publicPricePence() ?? 0);

        $salesCount = Sale::query()
            ->where('status', 'completed')
            ->whereHas('vehicle', fn ($q) => $q->where('dealer_id', $dealerId))
            ->count();

        $salesThisMonth = Sale::query()
            ->where('status', 'completed')
            ->whereDate('sold_on', '>=', $monthStart)
            ->whereHas('vehicle', fn ($q) => $q->where('dealer_id', $dealerId))
            ->sum('price_pence');

        $outstanding = Invoice::query()
            ->whereHas('customer', fn ($q) => $q->where('dealer_id', $dealerId))
            ->whereIn('status', ['issued', 'part_paid'])
            ->with('payments')
            ->get()
            ->sum(fn ($invoice) => $invoice->balancePence());

        $newLeads = Lead::query()
            ->whereHas('customer', fn ($q) => $q->where('dealer_id', $dealerId))
            ->where('status', 'new')
            ->count();

        $openBookings = Booking::query()
            ->whereHas('vehicle', fn ($q) => $q->where('dealer_id', $dealerId))
            ->whereIn('status', ['held', 'requested', 'confirmed'])
            ->count();

        $customersTotal = Customer::query()->where('dealer_id', $dealerId)->count();
        $customersMonth = Customer::query()
            ->where('dealer_id', $dealerId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $failedHooks = WebhookEvent::query()->where('dealer_id', $dealerId)->where('status', 'failed')->count();
        $lastSync = SyncRun::query()->where('dealer_id', $dealerId)->latest('id')->first();
        $activity = ActivityLog::query()->latest('id')->limit(10)->get();

        $attention = [
            'new_leads' => $newLeads,
            'open_bookings' => $openBookings,
            'failed_hooks' => $failedHooks,
            'outstanding' => $outstanding,
            'whatsapp_live' => $dealer->whatsappNumber() !== null,
            'webhook_set' => filled(config('autotrader.webhook_secret')),
        ];

        $charts = [
            'stock' => [
                'labels' => ['Available', 'Reserved', 'Preparation', 'Sold'],
                'values' => [
                    (int) ($counts['available'] ?? 0),
                    (int) ($counts['reserved'] ?? 0),
                    (int) ($counts['preparation'] ?? 0),
                    (int) ($counts['sold'] ?? 0),
                ],
            ],
            'leads' => $this->dailyLeadSeries($dealerId, 14),
            'sales' => $this->monthlySalesSeries($dealerId, 6),
            'bookings' => $this->bookingTypeBreakdown($dealerId),
        ];

        $recentLeads = Lead::query()
            ->with(['customer', 'vehicle'])
            ->whereHas('customer', fn ($q) => $q->where('dealer_id', $dealerId))
            ->latest('id')
            ->limit(6)
            ->get();

        $upcomingBookings = Booking::query()
            ->with(['customer', 'vehicle'])
            ->whereHas('vehicle', fn ($q) => $q->where('dealer_id', $dealerId))
            ->whereIn('status', ['held', 'requested', 'confirmed'])
            ->orderBy('preferred_on')
            ->limit(6)
            ->get();

        return view('dealer.overview', [
            'dealer' => $dealer,
            'counts' => $counts,
            'stockValue' => $stockValue,
            'salesCount' => $salesCount,
            'salesThisMonth' => $salesThisMonth,
            'outstanding' => $outstanding,
            'newLeads' => $newLeads,
            'openBookings' => $openBookings,
            'customersTotal' => $customersTotal,
            'customersMonth' => $customersMonth,
            'lastSync' => $lastSync,
            'activity' => $activity,
            'attention' => $attention,
            'charts' => $charts,
            'recentLeads' => $recentLeads,
            'upcomingBookings' => $upcomingBookings,
        ]);
    }

    public function settings(Request $request)
    {
        abort_unless($request->user('staff')->isAdmin(), 403);

        $dealer = $request->user('staff')->dealer->loadMissing('integration');
        $lastSync = SyncRun::query()->where('dealer_id', $dealer->id)->latest('id')->first();
        $blocks = ApiBlock::query()->where('integration_id', $dealer->integration_id)->whereNull('resolved_at')->count();
        $failedHooks = WebhookEvent::query()->where('dealer_id', $dealer->id)->where('status', 'failed')->count();
        $autotrader = $this->autotraderStatus($dealer);
        $syncRuns = SyncRun::query()->where('dealer_id', $dealer->id)->latest('id')->limit(25)->get();
        $webhookEvents = WebhookEvent::query()->where('dealer_id', $dealer->id)->orderByDesc('id')->limit(25)->get();
        $migrationStatus = $this->migrations->status();

        return view('dealer.settings', compact('dealer', 'autotrader', 'lastSync', 'blocks', 'failedHooks', 'syncRuns', 'webhookEvents', 'migrationStatus'));
    }

    public function updateSettings(Request $request)
    {
        $staff = $request->user('staff');
        abort_unless($staff->can('settings.manage'), 403);
        $dealer = $staff->dealer;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'trading_name' => ['nullable', 'string', 'max:160'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'postcode' => ['nullable', 'string', 'max:16'],
            'opening_hours' => ['nullable', 'string', 'max:255'],
            'website_host' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,ico', 'max:512'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ], [
            'logo.mimes' => 'The logo must be a PNG, JPG, or WebP image. SVG files are not supported.',
            'favicon.mimes' => 'The favicon must be a PNG, JPG, WebP, or ICO image. SVG files are not supported.',
        ]);

        $dealer->fill([
            'name' => $data['name'],
            'trading_name' => $data['trading_name'] ?? null,
            'tagline' => $data['tagline'] ?? null,
            'email' => isset($data['email']) ? strtolower($data['email']) : null,
            'phone' => $data['phone'],
            'address_line' => $data['address_line'] ?? null,
            'city' => $data['city'] ?? null,
            'postcode' => $data['postcode'] ?? null,
            'opening_hours' => $data['opening_hours'] ?? null,
            'website_host' => $data['website_host'] ?? null,
        ]);

        $folder = 'dealers/'.$dealer->id;

        if ($request->boolean('remove_logo') && $dealer->logo_path) {
            Storage::disk('public')->delete($dealer->logo_path);
            $dealer->logo_path = null;
        }
        if ($request->boolean('remove_favicon') && $dealer->favicon_path) {
            Storage::disk('public')->delete($dealer->favicon_path);
            $dealer->favicon_path = null;
        }

        if ($request->hasFile('logo')) {
            if ($dealer->logo_path) {
                Storage::disk('public')->delete($dealer->logo_path);
            }
            $dealer->logo_path = $request->file('logo')->store($folder, 'public');
        }
        if ($request->hasFile('favicon')) {
            if ($dealer->favicon_path) {
                Storage::disk('public')->delete($dealer->favicon_path);
            }
            $dealer->favicon_path = $request->file('favicon')->store($folder, 'public');
        }

        $dealer->save();
        $this->directory->log($staff->id, null, null, 'Website settings updated');

        return back()->with('status', 'Website settings saved.');
    }

    private function autotraderStatus(Dealer $dealer): array
    {
        $environment = (string) ($dealer->integration?->environment ?? config('autotrader.environment'));
        $webhookSet = filled(config('autotrader.webhook_secret'));
        $keySet = filled(config('autotrader.key')) && filled(config('autotrader.secret'));
        $expiresRaw = trim((string) config('autotrader.sandbox_expires_at'));
        $expiresAt = null;
        if ($expiresRaw !== '') {
            try {
                $expiresAt = Carbon::parse($expiresRaw)->startOfDay();
            } catch (\Throwable) {
                $expiresAt = null;
            }
        }

        $sandboxState = 'na';
        $sandboxLabel = 'Production — no sandbox expiry';
        if ($environment === 'sandbox') {
            if (! $expiresAt) {
                $sandboxState = 'unknown';
                $sandboxLabel = 'Not set — ask Autotrader for the sandbox end date';
            } elseif ($expiresAt->isPast()) {
                $sandboxState = 'expired';
                $sandboxLabel = 'Expired '.$expiresAt->format('j M Y');
            } elseif ($expiresAt->lte(now()->addDays(14))) {
                $sandboxState = 'soon';
                $sandboxLabel = 'Expires '.$expiresAt->format('j M Y').' (within 14 days)';
            } else {
                $sandboxState = 'ok';
                $sandboxLabel = 'Expires '.$expiresAt->format('j M Y');
            }
        }

        return [
            'environment' => $environment,
            'base_url' => (string) config('autotrader.base_url'),
            'key_set' => $keySet,
            'webhook_set' => $webhookSet,
            'advertiser_id' => $dealer->advertiser_id,
            'on_integration' => (bool) $dealer->on_integration,
            'sandbox_state' => $sandboxState,
            'sandbox_label' => $sandboxLabel,
            'whatsapp_live' => $dealer->whatsappNumber() !== null,
        ];
    }

    private function dailyLeadSeries(int $dealerId, int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $rows = Lead::query()
            ->whereHas('customer', fn ($q) => $q->where('dealer_id', $dealerId))
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $values = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->toDateString();
            $labels[] = $day->format('j M');
            $values[] = (int) ($rows[$key] ?? 0);
        }

        return compact('labels', 'values');
    }

    private function monthlySalesSeries(int $dealerId, int $months): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $rows = Sale::query()
            ->where('status', 'completed')
            ->whereDate('sold_on', '>=', $start->toDateString())
            ->whereHas('vehicle', fn ($q) => $q->where('dealer_id', $dealerId))
            ->selectRaw("DATE_FORMAT(sold_on, '%Y-%m') as month_key, SUM(price_pence) as total")
            ->groupBy('month_key')
            ->pluck('total', 'month_key');

        $labels = [];
        $values = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');
            $labels[] = $month->format('M Y');
            $values[] = round(((int) ($rows[$key] ?? 0)) / 100, 2);
        }

        return compact('labels', 'values');
    }

    private function bookingTypeBreakdown(int $dealerId): array
    {
        $rows = Booking::query()
            ->whereHas('vehicle', fn ($q) => $q->where('dealer_id', $dealerId))
            ->whereIn('status', ['held', 'requested', 'confirmed'])
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return [
            'labels' => ['Reservations', 'Viewings', 'Test drives'],
            'values' => [
                (int) ($rows['reservation'] ?? 0),
                (int) ($rows['viewing'] ?? 0),
                (int) ($rows['test_drive'] ?? 0),
            ],
        ];
    }

    public function vehicles(Request $request)
    {
        $dealerId = $request->user('staff')->dealer_id;
        $source = in_array($request->string('source')->toString(), ['autotrader', 'manual'], true)
            ? $request->string('source')->toString()
            : 'autotrader';
        $sourceCounts = Vehicle::query()->where('dealer_id', $dealerId)
            ->selectRaw('source, COUNT(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source');
        $cars = Vehicle::query()->where('dealer_id', $dealerId)
            ->where('source', $source)
            ->when($request->filled('status'), fn ($q) => $q->where('website_status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($q) => $q->where('registration', 'like', $term)->orWhere('make', 'like', $term)->orWhere('model', 'like', $term));
            })
            ->withExists('sales')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('dealer.vehicles.index', compact('cars', 'source', 'sourceCounts'));
    }

    public function createVehicle()
    {
        return view('dealer.vehicles.form', ['car' => new Vehicle(['source' => 'manual', 'website_status' => 'available'])]);
    }

    public function storeVehicle(Request $request)
    {
        $staff = $request->user('staff');
        $data = $this->manualRules($request);
        $price = Money::nullablePounds($data['price'] ?? null);
        $vehicle = new Vehicle([
            'dealer_id' => $staff->dealer_id,
            'environment' => $staff->dealer->integration->environment,
            'source' => 'manual',
            'show_on_website' => false,
            'registration' => $data['registration'] ?? null,
            'make' => $data['make'],
            'model' => $data['model'],
            'trim' => $data['trim'] ?? null,
            'year' => $data['year'] ?? null,
            'mileage' => $data['mileage'] ?? null,
            'fuel' => $data['fuel'] ?? null,
            'transmission' => $data['transmission'] ?? null,
            'body' => $data['body'] ?? null,
            'colour' => $data['colour'] ?? null,
            'cash_price_pence' => $price,
            'supplied_price_pence' => $price,
            'total_price_pence' => $price,
            'description' => $data['description'] ?? null,
            'purchase_cost_pence' => Money::poundsToPence($data['purchase_cost'] ?? 0),
            'prep_cost_pence' => Money::poundsToPence($data['prep_cost'] ?? 0),
            'internal_notes' => $data['internal_notes'] ?? null,
            'local_hold' => $data['website_status'] === 'preparation' ? 'preparation' : 'none',
            'website_status' => $data['website_status'],
            'created_by' => $staff->id,
            'updated_by' => $staff->id,
        ]);
        $this->status->apply($vehicle);
        $vehicle->save();
        $vehicle->slug = Str::slug(trim($vehicle->year.' '.$vehicle->make.' '.$vehicle->model)) ?: 'car';
        $vehicle->slug .= '-'.$vehicle->id;
        $vehicle->save();
        $this->directory->log($staff->id, $vehicle->id, null, 'Manual car added. It stays off the public site.');

        return redirect()->route('dealer.vehicles')->with('status', 'Manual car saved. It is hidden from the website.');
    }

    public function editVehicle(Request $request, Vehicle $vehicle)
    {
        abort_unless((int) $vehicle->dealer_id === (int) $request->user('staff')->dealer_id, 404);

        return view('dealer.vehicles.form', ['car' => $vehicle]);
    }

    public function updateVehicle(Request $request, Vehicle $vehicle)
    {
        $staff = $request->user('staff');
        abort_unless((int) $vehicle->dealer_id === (int) $staff->dealer_id, 404);

        if ($vehicle->source === 'manual') {
            $data = $this->manualRules($request);
            $price = Money::nullablePounds($data['price'] ?? null);
            $vehicle->fill([
                'registration' => $data['registration'] ?? null,
                'make' => $data['make'],
                'model' => $data['model'],
                'trim' => $data['trim'] ?? null,
                'year' => $data['year'] ?? null,
                'mileage' => $data['mileage'] ?? null,
                'fuel' => $data['fuel'] ?? null,
                'transmission' => $data['transmission'] ?? null,
                'body' => $data['body'] ?? null,
                'colour' => $data['colour'] ?? null,
                'description' => $data['description'] ?? null,
                'cash_price_pence' => $price,
                'supplied_price_pence' => $price,
                'total_price_pence' => $price,
                'website_status' => in_array($vehicle->website_status, ['reserved', 'sold'], true) ? $vehicle->website_status : $data['website_status'],
                'local_hold' => in_array($vehicle->website_status, ['reserved', 'sold'], true)
                    ? $vehicle->local_hold
                    : ($data['website_status'] === 'preparation' ? 'preparation' : 'none'),
            ]);
        }

        $costs = $request->validate([
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'prep_cost' => ['nullable', 'numeric', 'min:0'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'workshop' => ['nullable', 'boolean'],
            'featured' => ['sometimes', 'boolean'],
        ]);
        $vehicle->purchase_cost_pence = Money::poundsToPence($costs['purchase_cost'] ?? 0);
        $vehicle->prep_cost_pence = Money::poundsToPence($costs['prep_cost'] ?? 0);
        $vehicle->internal_notes = $costs['internal_notes'] ?? null;
        if ($staff->can('vehicles.feature') && array_key_exists('featured', $costs)) {
            $vehicle->featured = $request->boolean('featured');
        }
        if ($vehicle->source === 'autotrader' && $vehicle->local_hold !== 'reserved' && $vehicle->website_status !== 'sold') {
            $vehicle->local_hold = $request->boolean('workshop') ? 'preparation' : 'none';
        }
        $vehicle->updated_by = $staff->id;
        $this->status->apply($vehicle);
        $vehicle->save();

        return redirect()->route('dealer.vehicles.edit', $vehicle)->with('status', 'Vehicle saved.');
    }

    public function featureVehicle(Request $request, Vehicle $vehicle)
    {
        $staff = $request->user('staff');
        abort_unless((int) $vehicle->dealer_id === (int) $staff->dealer_id, 404);
        $request->validate(['featured' => ['sometimes', 'boolean']]);
        $vehicle->featured = $request->boolean('featured');
        $vehicle->updated_by = $staff->id;
        $vehicle->save();

        return back()->with('status', $vehicle->featured ? 'Car added to the homepage selection.' : 'Car removed from the homepage selection.');
    }

    public function destroyVehicle(Request $request, Vehicle $vehicle)
    {
        $staff = $request->user('staff');
        abort_unless((int) $vehicle->dealer_id === (int) $staff->dealer_id, 404);
        if ($vehicle->source !== 'manual') {
            throw ValidationException::withMessages([
                'vehicle' => 'Synced Autotrader cars are hidden by a baseline. They are not deleted here.',
            ]);
        }
        if ($vehicle->sales()->exists()) {
            throw ValidationException::withMessages([
                'vehicle' => 'A car with a sale stays on record.',
            ]);
        }

        DB::transaction(function () use ($vehicle, $staff) {
            $vehicle->bookings()->delete();
            SavedVehicle::query()->where('vehicle_id', $vehicle->id)->delete();
            Lead::query()->where('vehicle_id', $vehicle->id)->update(['vehicle_id' => null]);
            VehiclePayload::query()->where('vehicle_id', $vehicle->id)->delete();
            VehiclePriceHistory::query()->where('vehicle_id', $vehicle->id)->delete();
            ActivityLog::query()->where('vehicle_id', $vehicle->id)->update(['vehicle_id' => null]);
            WebhookEvent::query()->where('vehicle_id', $vehicle->id)->update(['vehicle_id' => null]);
            $label = $vehicle->title();
            $vehicle->delete();
            $this->directory->log($staff->id, null, null, 'Manual car removed: '.$label);
        });

        return redirect()->route('dealer.vehicles')->with('status', 'Manual car removed.');
    }

    public function sync(Request $request)
    {
        $staff = $request->user('staff');
        abort_unless($staff->can('sync.run'), 403);
        try {
            $this->sync->baseline($staff->dealer, $staff->id);
        } catch (AutotraderException $exception) {
            return back()->withErrors(['sync' => $exception->getMessage()]);
        }

        return back()->with('status', 'Baseline finished.');
    }

    public function migrateDatabase(Request $request)
    {
        $staff = $request->user('staff');
        abort_unless($staff->isAdmin() && $staff->can('settings.manage'), 403);

        try {
            $result = $this->migrations->runPendingMigrations();
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('dealer.settings')
                ->withFragment('settings-database')
                ->withErrors(['migrate' => $exception->getMessage()])
                ->with('migration_output', $exception->getMessage());
        }

        $status = $result['ran']
            ? 'Pending migrations applied.'
            : 'No pending migrations.';

        return redirect()
            ->route('dealer.settings')
            ->withFragment('settings-database')
            ->with('status', $status)
            ->with('migration_output', (string) ($result['output'] ?? ''));
    }

    public function bookings(Request $request)
    {
        $dealerId = $request->user('staff')->dealer_id;
        $month = Carbon::parse($request->input('month', now()->format('Y-m').'-01'))->startOfMonth();
        $view = $request->input('view', 'list');

        $rows = Booking::query()->whereHas('vehicle', fn ($q) => $q->where('dealer_id', $dealerId))
            ->with(['vehicle', 'customer'])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $calendarBookings = Booking::query()
            ->whereHas('vehicle', fn ($q) => $q->where('dealer_id', $dealerId))
            ->with(['vehicle', 'customer'])
            ->whereBetween('preferred_on', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('preferred_on')
            ->orderBy('preferred_time')
            ->get()
            ->groupBy(fn ($b) => $b->preferred_on?->format('Y-m-d'));

        return view('dealer.bookings', [
            'rows' => $rows,
            'view' => $view,
            'month' => $month,
            'calendarBookings' => $calendarBookings,
            'cars' => Vehicle::query()->where('dealer_id', $dealerId)->whereIn('website_status', ['available', 'reserved'])->orderBy('make')->get(),
            'customers' => Customer::query()->where('dealer_id', $dealerId)->orderBy('name')->get(),
        ]);
    }

    public function showBooking(Request $request, Booking $booking)
    {
        $staff = $request->user('staff');
        $booking->load(['vehicle.images', 'customer']);
        abort_unless($booking->vehicle && (int) $booking->vehicle->dealer_id === (int) $staff->dealer_id, 404);

        $customerId = $booking->customer_id;

        $leads = Lead::query()
            ->where('customer_id', $customerId)
            ->with('vehicle')
            ->latest('id')
            ->limit(8)
            ->get();

        $otherBookings = Booking::query()
            ->where('customer_id', $customerId)
            ->whereKeyNot($booking->id)
            ->with('vehicle')
            ->latest('id')
            ->limit(8)
            ->get();

        $sales = Sale::query()
            ->where('customer_id', $customerId)
            ->with('vehicle')
            ->latest('id')
            ->limit(8)
            ->get();

        $invoices = Invoice::query()
            ->where('customer_id', $customerId)
            ->latest('id')
            ->limit(8)
            ->get();

        return view('dealer.bookings.show', compact('booking', 'leads', 'otherBookings', 'sales', 'invoices'));
    }

    public function export(Request $request, string $type)
    {
        $staff = $request->user('staff');
        $ability = match ($type) {
            'customers' => 'customers.view',
            'leads' => 'leads.view',
            'bookings' => 'bookings.view',
            'sales' => 'sales.view',
            'vehicles' => 'vehicles.view',
            default => null,
        };
        abort_unless($ability && $staff->can($ability), 403);

        $dealerId = $staff->dealer_id;
        $filename = $type.'-'.now()->format('Ymd-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = match ($type) {
            'customers' => function () use ($dealerId) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['id', 'name', 'email', 'phone', 'postcode', 'source', 'created_at']);
                Customer::query()->where('dealer_id', $dealerId)->orderBy('id')->chunk(200, function ($rows) use ($out) {
                    foreach ($rows as $row) {
                        fputcsv($out, [$row->id, $row->name, $row->email, $row->phone, $row->postcode, $row->source, $row->created_at]);
                    }
                });
                fclose($out);
            },
            'leads' => function () use ($dealerId) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['id', 'type', 'status', 'subject', 'customer', 'email', 'vehicle', 'created_at']);
                Lead::query()->whereHas('customer', fn ($q) => $q->where('dealer_id', $dealerId))
                    ->with(['customer', 'vehicle'])
                    ->orderBy('id')
                    ->chunk(200, function ($rows) use ($out) {
                        foreach ($rows as $row) {
                            fputcsv($out, [
                                $row->id,
                                $row->type,
                                $row->status,
                                $row->subject,
                                $row->customer?->name,
                                $row->customer?->email,
                                $row->vehicle?->title(),
                                $row->created_at,
                            ]);
                        }
                    });
                fclose($out);
            },
            'bookings' => function () use ($dealerId) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['id', 'type', 'status', 'preferred_on', 'preferred_time', 'customer', 'vehicle']);
                Booking::query()->whereHas('vehicle', fn ($q) => $q->where('dealer_id', $dealerId))
                    ->with(['customer', 'vehicle'])
                    ->orderBy('id')
                    ->chunk(200, function ($rows) use ($out) {
                        foreach ($rows as $row) {
                            fputcsv($out, [
                                $row->id,
                                $row->type,
                                $row->status,
                                optional($row->preferred_on)->format('Y-m-d'),
                                $row->preferred_time,
                                $row->customer?->name,
                                $row->vehicle?->title(),
                            ]);
                        }
                    });
                fclose($out);
            },
            'sales' => function () use ($dealerId) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['id', 'sold_on', 'price_pence', 'method', 'status', 'customer', 'vehicle']);
                Sale::query()->whereHas('vehicle', fn ($q) => $q->where('dealer_id', $dealerId))
                    ->with(['customer', 'vehicle'])
                    ->orderBy('id')
                    ->chunk(200, function ($rows) use ($out) {
                        foreach ($rows as $row) {
                            fputcsv($out, [
                                $row->id,
                                optional($row->sold_on)->format('Y-m-d'),
                                $row->price_pence,
                                $row->method,
                                $row->status,
                                $row->customer?->name,
                                $row->vehicle?->title() ?: $row->vehicle_label,
                            ]);
                        }
                    });
                fclose($out);
            },
            'vehicles' => function () use ($dealerId) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['id', 'source', 'make', 'model', 'year', 'registration', 'website_status', 'price_pence', 'featured']);
                Vehicle::query()->where('dealer_id', $dealerId)->orderBy('id')->chunk(200, function ($rows) use ($out) {
                    foreach ($rows as $row) {
                        fputcsv($out, [
                            $row->id,
                            $row->source,
                            $row->make,
                            $row->model,
                            $row->year,
                            $row->registration,
                            $row->website_status,
                            $row->publicPricePence(),
                            $row->featured ? 1 : 0,
                        ]);
                    }
                });
                fclose($out);
            },
            default => abort(404),
        };

        return response()->stream($callback, 200, $headers);
    }

    public function storeBooking(Request $request)
    {
        $staff = $request->user('staff');
        $data = $request->validate([
            'vehicle_id' => ['required', 'integer'],
            'customer_id' => ['required', 'integer'],
            'type' => ['required', 'in:reservation,test_drive,viewing'],
            'preferred_on' => ['required', 'date'],
            'preferred_time' => ['required_unless:type,reservation', 'nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $vehicle = Vehicle::query()->where('dealer_id', $staff->dealer_id)->findOrFail($data['vehicle_id']);
        $customer = Customer::query()->where('dealer_id', $staff->dealer_id)->findOrFail($data['customer_id']);
        $dealer = $staff->dealer;
        try {
            if ($data['type'] === 'reservation') {
                $booking = $this->bookings->reserve($vehicle, $customer, $data['preferred_on'], $data['notes'] ?? null, $staff);
                $this->mailer->reservationCustomer($customer, $booking, $vehicle);
                $this->mailer->reservationDealer($dealer, $customer, $booking, $vehicle);
            } else {
                $booking = $this->bookings->appointment($vehicle, $customer, $data['type'], $data['preferred_on'], $data['preferred_time'], $data['notes'] ?? null, $staff);
                $this->mailer->appointmentCustomer($customer, $booking, $vehicle);
                $this->mailer->appointmentDealer($dealer, $customer, $booking, $vehicle);
            }
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['preferred_time' => 'That booking clashes with one already held.']);
        }

        $this->notifier->notifyStaff(
            $dealer,
            'booking.new',
            'New '.str_replace('_', ' ', $data['type']).': '.$vehicle->title(),
            $customer->name,
            route('dealer.bookings')
        );

        return back()->with('status', 'Booking saved.');
    }

    public function bookingStatus(Request $request, Booking $booking)
    {
        $staff = $request->user('staff');
        abort_unless((int) $booking->vehicle->dealer_id === (int) $staff->dealer_id, 404);
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in($booking->nextStatuses())],
        ]);
        $this->bookings->transition($booking, $data['status'], $staff);

        return back()->with('status', 'Booking updated.');
    }

    public function customers(Request $request)
    {
        $dealerId = $request->user('staff')->dealer_id;
        $rows = Customer::query()->where('dealer_id', $dealerId)
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->latest('id')
            ->paginate(20);

        return view('dealer.customers', compact('rows'));
    }

    public function showCustomer(Request $request, Customer $customer)
    {
        $staff = $request->user('staff');
        abort_unless($staff->can('customers.view'), 403);
        abort_unless((int) $customer->dealer_id === (int) $staff->dealer_id, 404);

        $customer->load([
            'leads.vehicle',
            'leads.valuation',
            'bookings.vehicle',
        ]);
        $invoices = Invoice::query()->where('customer_id', $customer->id)->latest('id')->limit(20)->get();
        $sales = Sale::query()->where('customer_id', $customer->id)->with('vehicle')->latest('id')->limit(20)->get();
        $activity = ActivityLog::query()->where('customer_id', $customer->id)->latest('id')->limit(30)->get();

        return view('dealer.customers.show', compact('customer', 'invoices', 'sales', 'activity'));
    }

    public function updateCustomer(Request $request, Customer $customer)
    {
        $staff = $request->user('staff');
        abort_unless($staff->can('customers.manage'), 403);
        abort_unless((int) $customer->dealer_id === (int) $staff->dealer_id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            'postcode' => ['nullable', 'string', 'max:16'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'source' => ['required', 'in:showroom,phone,website_enquiry,finance_enquiry,sell_your_car,other'],
        ]);
        $customer->fill($data);
        $customer->save();
        $this->directory->log($staff->id, null, $customer->id, 'Customer updated');

        return back()->with('status', 'Customer updated.');
    }

    public function storeCustomer(Request $request)
    {
        $staff = $request->user('staff');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['required', 'string', 'max:40'],
            'postcode' => ['nullable', 'string', 'max:16'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'source' => ['required', 'in:showroom,phone,website_enquiry,finance_enquiry,sell_your_car,other'],
        ], [
            'email.unique' => 'That email is already used by another customer.',
        ]);
        $customer = Customer::query()->create($data + ['dealer_id' => $staff->dealer_id]);
        $this->directory->log($staff->id, null, $customer->id, 'Customer added');

        return back()->with('status', 'Customer saved.');
    }

    public function leads(Request $request)
    {
        $dealerId = $request->user('staff')->dealer_id;
        $rows = Lead::query()->whereHas('customer', fn ($q) => $q->where('dealer_id', $dealerId))
            ->with(['customer', 'vehicle', 'valuation.photos'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('id')
            ->paginate(20);

        return view('dealer.leads', compact('rows'));
    }

    public function showLead(Request $request, Lead $lead)
    {
        $staff = $request->user('staff');
        abort_unless($staff->can('leads.view'), 403);
        abort_unless((int) $lead->customer->dealer_id === (int) $staff->dealer_id, 404);
        $lead->load(['customer', 'vehicle', 'valuation.photos', 'assignee']);

        return view('dealer.leads.show', [
            'lead' => $lead,
            'requirementKeys' => Lead::REQUIREMENT_KEYS,
        ]);
    }

    public function updateLead(Request $request, Lead $lead)
    {
        $staff = $request->user('staff');
        abort_unless($staff->can('leads.manage'), 403);
        abort_unless((int) $lead->customer->dealer_id === (int) $staff->dealer_id, 404);
        $data = $request->validate([
            'status' => ['required', 'in:new,contacted,closed'],
            'contact_notes' => [
                Rule::requiredIf(fn () => $request->input('status') === 'contacted' && ! filled($lead->contact_notes)),
                'nullable',
                'string',
                'max:5000',
            ],
            'additional_notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'contact_notes.required' => 'Add contact notes when marking a lead as contacted.',
        ]);

        $previous = $lead->status;
        $lead->status = $data['status'];
        $lead->contact_notes = $data['contact_notes'] ?? $lead->contact_notes;
        $lead->additional_notes = $data['additional_notes'] ?? $lead->additional_notes;
        $lead->assigned_to = $staff->id;
        if ($previous !== 'contacted' && $data['status'] === 'contacted') {
            $lead->contacted_at = now();
        }
        $lead->save();

        // Persist status first; notify after so a mail/DB notify failure cannot roll back the workflow.
        if ($previous !== 'contacted' && $data['status'] === 'contacted') {
            try {
                $lead->loadMissing(['customer', 'vehicle']);
                if ($lead->customer) {
                    $this->mailer->leadContacted($lead->customer, $lead);
                    $this->notifier->notifyCustomer(
                        $lead->customer,
                        'lead.contacted',
                        'We are following up on your enquiry',
                        $lead->subject,
                        route('account')
                    );
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return back()->with('status', 'Lead updated.');
    }

    public function storeLeadRequirement(Request $request, Lead $lead)
    {
        $staff = $request->user('staff');
        abort_unless($staff->can('leads.manage'), 403);
        abort_unless((int) $lead->customer->dealer_id === (int) $staff->dealer_id, 404);

        $data = $request->validate([
            'key' => ['required', 'string', 'max:40'],
            'custom_key' => ['nullable', 'string', 'max:40'],
            'value' => ['required', 'string', 'max:120'],
        ]);

        $key = $data['key'] === 'custom'
            ? Str::slug((string) ($data['custom_key'] ?? ''), '_')
            : $data['key'];
        if ($key === '') {
            throw ValidationException::withMessages(['custom_key' => 'Enter a label for this detail.']);
        }

        $requirements = (array) $lead->requirements;
        $requirements[$key] = $data['value'];
        $lead->requirements = $requirements;
        $lead->assigned_to = $staff->id;
        $lead->save();

        return back()->with('status', 'Requirement saved.');
    }

    public function updateLeadRequirement(Request $request, Lead $lead, string $key)
    {
        $staff = $request->user('staff');
        abort_unless($staff->can('leads.manage'), 403);
        abort_unless((int) $lead->customer->dealer_id === (int) $staff->dealer_id, 404);
        $data = $request->validate(['value' => ['required', 'string', 'max:120']]);
        $requirements = (array) $lead->requirements;
        if (! array_key_exists($key, $requirements)) {
            abort(404);
        }
        $requirements[$key] = $data['value'];
        $lead->requirements = $requirements;
        $lead->save();

        return back()->with('status', 'Requirement updated.');
    }

    public function destroyLeadRequirement(Request $request, Lead $lead, string $key)
    {
        $staff = $request->user('staff');
        abort_unless($staff->can('leads.manage'), 403);
        abort_unless((int) $lead->customer->dealer_id === (int) $staff->dealer_id, 404);
        $requirements = (array) $lead->requirements;
        unset($requirements[$key]);
        $lead->requirements = $requirements;
        $lead->save();

        return back()->with('status', 'Requirement removed.');
    }

    public function sales(Request $request)
    {
        $dealerId = $request->user('staff')->dealer_id;
        $rows = Sale::query()->whereHas('vehicle', fn ($q) => $q->where('dealer_id', $dealerId))
            ->with(['vehicle', 'customer', 'invoice'])
            ->latest('id')
            ->paginate(20);

        return view('dealer.sales', [
            'rows' => $rows,
            'cars' => Vehicle::query()->where('dealer_id', $dealerId)->where('website_status', '!=', 'sold')->orderBy('make')->get(),
            'customers' => Customer::query()->where('dealer_id', $dealerId)->orderBy('name')->get(),
            'valuations' => Valuation::query()->whereHas('lead.customer', fn ($q) => $q->where('dealer_id', $dealerId))->latest('id')->limit(50)->get(),
        ]);
    }

    public function showSale(Request $request, Sale $sale)
    {
        $staff = $request->user('staff');
        $sale->load(['vehicle.images', 'customer', 'invoice', 'partExchangeValuation']);
        abort_unless($sale->vehicle && (int) $sale->vehicle->dealer_id === (int) $staff->dealer_id, 404);

        $customerId = $sale->customer_id;

        $leads = Lead::query()
            ->where('customer_id', $customerId)
            ->with('vehicle')
            ->latest('id')
            ->limit(8)
            ->get();

        $bookings = Booking::query()
            ->where('customer_id', $customerId)
            ->with('vehicle')
            ->latest('id')
            ->limit(8)
            ->get();

        $otherSales = Sale::query()
            ->where('customer_id', $customerId)
            ->whereKeyNot($sale->id)
            ->with('vehicle')
            ->latest('id')
            ->limit(8)
            ->get();

        $invoices = Invoice::query()
            ->where('customer_id', $customerId)
            ->latest('id')
            ->limit(8)
            ->get();

        return view('dealer.sales.show', compact('sale', 'leads', 'bookings', 'otherSales', 'invoices'));
    }

    public function storeSale(Request $request)
    {
        $staff = $request->user('staff');
        $data = $request->validate([
            'vehicle_id' => ['required', 'integer'],
            'customer_id' => ['required', 'integer'],
            'price' => ['required', 'numeric', 'min:0'],
            'sold_on' => ['required', 'date'],
            'method' => ['required', 'in:bank_transfer,card,finance,cash'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'part_exchange_valuation_id' => ['nullable', 'integer'],
        ]);
        $vehicle = Vehicle::query()->where('dealer_id', $staff->dealer_id)->findOrFail($data['vehicle_id']);
        $customer = Customer::query()->where('dealer_id', $staff->dealer_id)->findOrFail($data['customer_id']);
        $this->invoices->completeSale($staff->dealer, $vehicle, $customer, [
            'price_pence' => Money::poundsToPence($data['price']),
            'sold_on' => $data['sold_on'],
            'method' => $data['method'],
            'notes' => $data['notes'] ?? null,
            'part_exchange_valuation_id' => $data['part_exchange_valuation_id'] ?? null,
        ], $staff);

        return back()->with('status', 'Sale recorded and the car is now sold.');
    }

    private function manualRules(Request $request): array
    {
        return $request->validate([
            'registration' => ['nullable', 'string', 'max:16'],
            'make' => ['required', 'string', 'max:80'],
            'model' => ['required', 'string', 'max:80'],
            'trim' => ['nullable', 'string', 'max:160'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 1)],
            'mileage' => ['nullable', 'integer', 'min:0'],
            'fuel' => ['nullable', 'string', 'max:80'],
            'transmission' => ['nullable', 'string', 'max:80'],
            'body' => ['nullable', 'string', 'max:80'],
            'colour' => ['nullable', 'string', 'max:80'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:10000'],
            'website_status' => ['required', 'in:available,preparation'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'prep_cost' => ['nullable', 'numeric', 'min:0'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
