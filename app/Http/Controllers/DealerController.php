<?php

namespace App\Http\Controllers;

use App\Exceptions\AutotraderException;
use App\Models\ActivityLog;
use App\Models\ApiBlock;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\SavedVehicle;
use App\Models\SyncRun;
use App\Models\Valuation;
use App\Models\Vehicle;
use App\Models\VehiclePayload;
use App\Models\VehiclePriceHistory;
use App\Models\WebhookEvent;
use App\Services\Autotrader\StockSync;
use App\Services\BookingService;
use App\Services\CustomerDirectory;
use App\Services\InvoiceService;
use App\Services\VehicleStatus;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DealerController extends Controller
{
    public function __construct(
        private VehicleStatus $status,
        private CustomerDirectory $directory,
        private BookingService $bookings,
        private InvoiceService $invoices,
        private StockSync $sync,
    ) {}

    public function showLogin()
    {
        return view('dealer.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        if (! Auth::guard('staff')->attempt(['email' => strtolower($data['email']), 'password' => $data['password']])) {
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
        $counts = Vehicle::query()->where('dealer_id', $dealer->id)
            ->selectRaw('website_status, COUNT(*) as total')
            ->groupBy('website_status')
            ->pluck('total', 'website_status');
        $stockValue = Vehicle::query()
            ->where('dealer_id', $dealer->id)
            ->whereIn('website_status', ['available', 'reserved', 'preparation'])
            ->get()
            ->sum(fn (Vehicle $vehicle) => $vehicle->publicPricePence() ?? 0);
        $sales = Sale::query()->where('status', 'completed')->whereHas('vehicle', fn ($q) => $q->where('dealer_id', $dealer->id))->count();
        $outstanding = \App\Models\Invoice::query()
            ->whereHas('customer', fn ($q) => $q->where('dealer_id', $dealer->id))
            ->whereIn('status', ['issued', 'part_paid'])
            ->with('payments')
            ->get()
            ->sum(fn ($invoice) => $invoice->balancePence());
        $lastSync = SyncRun::query()->where('dealer_id', $dealer->id)->latest('id')->first();
        $blocks = ApiBlock::query()->where('integration_id', $dealer->integration_id)->whereNull('resolved_at')->count();
        $failedHooks = WebhookEvent::query()->where('dealer_id', $dealer->id)->where('status', 'failed')->count();
        $activity = \App\Models\ActivityLog::query()->latest('id')->limit(8)->get();

        return view('dealer.overview', compact('dealer', 'counts', 'stockValue', 'sales', 'outstanding', 'lastSync', 'blocks', 'failedHooks', 'activity'));
    }

    public function updatePhone(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40', function (string $attribute, mixed $value, \Closure $fail): void {
                $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
                if (in_array($digits, ['02000000000', '442000000000', '440200000000'], true) || strlen($digits) < 10) {
                    $fail('Enter the dealership phone number, including the area code.');
                }
            }],
        ]);
        $dealer = $request->user('staff')->dealer;
        $dealer->phone = $data['phone'];
        $dealer->save();

        return back()->with('status', 'Phone number saved. WhatsApp appears on the website when the number can be dialled.');
    }

    public function vehicles(Request $request)
    {
        $dealerId = $request->user('staff')->dealer_id;
        $cars = Vehicle::query()->where('dealer_id', $dealerId)
            ->when($request->filled('status'), fn ($q) => $q->where('website_status', $request->string('status')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->string('source')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($q) => $q->where('registration', 'like', $term)->orWhere('make', 'like', $term)->orWhere('model', 'like', $term));
            })
            ->withExists('sales')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('dealer.vehicles.index', compact('cars'));
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
        ]);
        $vehicle->purchase_cost_pence = Money::poundsToPence($costs['purchase_cost'] ?? 0);
        $vehicle->prep_cost_pence = Money::poundsToPence($costs['prep_cost'] ?? 0);
        $vehicle->internal_notes = $costs['internal_notes'] ?? null;
        if ($vehicle->source === 'autotrader' && $vehicle->local_hold !== 'reserved' && $vehicle->website_status !== 'sold') {
            $vehicle->local_hold = $request->boolean('workshop') ? 'preparation' : 'none';
        }
        $vehicle->updated_by = $staff->id;
        $this->status->apply($vehicle);
        $vehicle->save();

        return redirect()->route('dealer.vehicles.edit', $vehicle)->with('status', 'Vehicle saved.');
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
        abort_unless($staff->isAdmin(), 403);
        try {
            $this->sync->baseline($staff->dealer, $staff->id);
        } catch (AutotraderException $exception) {
            return back()->withErrors(['sync' => $exception->getMessage()]);
        }

        return back()->with('status', 'Baseline finished.');
    }

    public function bookings(Request $request)
    {
        $dealerId = $request->user('staff')->dealer_id;
        $rows = Booking::query()->whereHas('vehicle', fn ($q) => $q->where('dealer_id', $dealerId))
            ->with(['vehicle', 'customer'])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('id')
            ->paginate(20);

        return view('dealer.bookings', [
            'rows' => $rows,
            'cars' => Vehicle::query()->where('dealer_id', $dealerId)->whereIn('website_status', ['available', 'reserved'])->orderBy('make')->get(),
            'customers' => Customer::query()->where('dealer_id', $dealerId)->orderBy('name')->get(),
        ]);
    }

    public function storeBooking(Request $request)
    {
        $staff = $request->user('staff');
        $data = $request->validate([
            'vehicle_id' => ['required', 'integer'],
            'customer_id' => ['required', 'integer'],
            'type' => ['required', 'in:reservation,test_drive,viewing'],
            'preferred_on' => ['required', 'date'],
            'preferred_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $vehicle = Vehicle::query()->where('dealer_id', $staff->dealer_id)->findOrFail($data['vehicle_id']);
        $customer = Customer::query()->where('dealer_id', $staff->dealer_id)->findOrFail($data['customer_id']);
        try {
            if ($data['type'] === 'reservation') {
                $this->bookings->reserve($vehicle, $customer, $data['preferred_on'], $data['notes'] ?? null, $staff);
            } else {
                if (empty($data['preferred_time'])) {
                    throw ValidationException::withMessages(['preferred_time' => 'Choose a time.']);
                }
                $this->bookings->appointment($vehicle, $customer, $data['type'], $data['preferred_on'], $data['preferred_time'], $data['notes'] ?? null, $staff);
            }
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['preferred_time' => 'That booking clashes with one already held.']);
        }

        return back()->with('status', 'Booking saved.');
    }

    public function bookingStatus(Request $request, Booking $booking)
    {
        $staff = $request->user('staff');
        abort_unless((int) $booking->vehicle->dealer_id === (int) $staff->dealer_id, 404);
        $data = $request->validate(['status' => ['required', 'string']]);
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

    public function updateLead(Request $request, Lead $lead)
    {
        $staff = $request->user('staff');
        abort_unless((int) $lead->customer->dealer_id === (int) $staff->dealer_id, 404);
        $data = $request->validate(['status' => ['required', 'in:new,contacted,closed']]);
        $lead->status = $data['status'];
        $lead->assigned_to = $staff->id;
        $lead->save();

        return back()->with('status', 'Lead updated.');
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
