<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\Dealer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\Valuation;
use App\Models\ValuationPhoto;
use App\Models\Vehicle;
use App\Services\AppNotifier;
use App\Services\BookingService;
use App\Services\CustomerDirectory;
use App\Services\Mail\GhaniMailer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SiteController extends Controller
{
    public function __construct(
        private CustomerDirectory $directory,
        private BookingService $bookings,
        private GhaniMailer $mailer,
        private AppNotifier $notifier,
    ) {}

    public function home()
    {
        $dealer = Dealer::current();
        // Homepage shows featured stock only. Search/filters submit to /stock via the home form.
        $cars = $dealer->vehicles()->public()->where('featured', true)->with('images')->latest('id')->limit(12)->get();

        return view('site.home', array_merge(
            ['dealer' => $dealer, 'cars' => $cars],
            $this->searchChoices($dealer->vehicles()->public())
        ));
    }

    public function stock(Request $request)
    {
        $dealer = Dealer::current();
        $fragment = $request->boolean('fragment');
        $request->query->remove('fragment');
        $public = $dealer->vehicles()->public();
        $cars = $this->filtered($request, $dealer)->paginate(12)->withQueryString();
        if ($fragment) {
            return view('partials.stock-results', compact('cars'));
        }

        return view('site.stock', array_merge(
            [
                'dealer' => $dealer,
                'cars' => $cars,
                'saved' => $this->directory->savedIds($request->user()),
                'stockTotal' => (clone $public)->count(),
            ],
            $this->searchChoices($public)
        ));
    }

    public function show(Request $request, string $slug)
    {
        $dealer = Dealer::current();
        $car = $dealer->vehicles()->public()->where('slug', $slug)->with(['images', 'features', 'highlights', 'chargeTimes'])->firstOrFail();
        $saved = in_array($car->id, $this->directory->savedIds($request->user()), true);

        return view('site.car', compact('dealer', 'car', 'saved'));
    }

    public function save(Request $request, string $slug)
    {
        $dealer = Dealer::current();
        $car = $dealer->vehicles()->public()->where('slug', $slug)->firstOrFail();
        $saved = $this->directory->toggleSaved($dealer, $car->id, $request->user());
        $ids = $this->directory->savedIds($request->user());
        $message = $saved ? 'Car saved to your account.' : 'Car removed from saved list.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'saved' => $saved,
                'vehicle_id' => $car->id,
                'ids' => $ids,
                'count' => count($ids),
                'message' => $message,
            ]);
        }

        return back()->with('status', $message);
    }

    public function enquiry(Request $request, string $slug)
    {
        $dealer = Dealer::current();
        $car = $dealer->vehicles()->public()->where('slug', $slug)->firstOrFail();
        $data = $this->contact($request);
        $customer = $request->user() ?? $this->directory->findOrCreate($dealer, $data['name'], $data['email'], $data['phone'], 'website_enquiry');
        $lead = Lead::query()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $car->id,
            'type' => 'general',
            'subject' => 'Enquiry: '.$car->title(),
            'message' => $data['message'],
            'accepted_terms_at' => now(),
            'created_on' => now()->toDateString(),
        ]);
        $this->mailer->enquiryCustomer($customer, $lead, $car);
        $this->mailer->enquiryDealer($dealer, $customer, $lead, $car);
        $this->notifier->notifyStaff($dealer, 'lead.new', 'New enquiry: '.$car->title(), $customer->name, route('dealer.leads.show', $lead));

        return back()->with('status', 'Thanks. The team has your enquiry.');
    }

    public function reserve(Request $request, string $slug)
    {
        $dealer = Dealer::current();
        $car = $dealer->vehicles()->public()->where('slug', $slug)->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email'],
            'phone' => ['required', 'string', 'max:40'],
            'preferred_on' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'accepted_terms' => ['accepted'],
        ]);
        $customer = $request->user();
        // reserve() is typed to return Booking and throws on failure — never returns null.
        $booking = $this->bookings->reserve($car, $customer, $data['preferred_on'], $data['notes'] ?? null);
        try {
            $this->mailer->reservationCustomer($customer, $booking, $car);
            $this->mailer->reservationDealer($dealer, $customer, $booking, $car);
            $this->notifier->notifyStaff($dealer, 'booking.new', 'New reservation: '.$car->title(), $customer->name, route('dealer.bookings'));
            $this->notifier->notifyCustomer($customer, 'booking.new', 'Reservation held: '.$car->title(), null, route('account'));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('status', 'This car is reserved for you. We will be in touch to confirm.');
    }

    public function testDrive(Request $request, string $slug)
    {
        return $this->bookAppointment($request, $slug, 'test_drive');
    }

    public function viewing(Request $request, string $slug)
    {
        return $this->bookAppointment($request, $slug, 'viewing');
    }

    public function generalEnquiry(Request $request)
    {
        $dealer = Dealer::current();
        $data = $this->contact($request);
        $customer = $request->user() ?? $this->directory->findOrCreate($dealer, $data['name'], $data['email'], $data['phone'], 'website_enquiry');
        $lead = Lead::query()->create([
            'customer_id' => $customer->id,
            'type' => 'general',
            'subject' => 'Website enquiry',
            'message' => $data['message'],
            'accepted_terms_at' => now(),
            'created_on' => now()->toDateString(),
        ]);
        $this->mailer->enquiryCustomer($customer, $lead);
        $this->mailer->enquiryDealer($dealer, $customer, $lead);
        $this->notifier->notifyStaff($dealer, 'lead.new', 'New website enquiry', $customer->name, route('dealer.leads.show', $lead));

        return back()->with('status', 'Thanks. The team has your enquiry.');
    }

    public function sell()
    {
        return view('site.sell', ['dealer' => Dealer::current()]);
    }

    public function storeSell(Request $request)
    {
        $dealer = Dealer::current();
        $data = $request->validate([
            'registration' => ['required', 'string', 'max:16'],
            'mileage' => ['required', 'integer', 'min:0'],
            'make' => ['required', 'string', 'max:80'],
            'model' => ['required', 'string', 'max:80'],
            'year' => ['required', 'integer', 'min:1950', 'max:'.(date('Y') + 1)],
            'fuel' => ['required', 'string', 'max:80'],
            'transmission' => ['required', 'string', 'max:80'],
            'intent' => ['required', 'in:sell,part_exchange'],
            'condition' => ['required', 'in:excellent,good,fair,needs_attention'],
            'service_history' => ['required', 'in:full,partial,none,not_sure'],
            'mot_expiry' => ['nullable', 'date'],
            'outstanding_finance' => ['required', 'in:yes,no,not_sure'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email'],
            'phone' => ['required', 'string', 'max:40'],
            'postcode' => ['required', 'string', 'max:16'],
            'preferred_contact' => ['required', 'in:phone,email'],
            'accepted_terms' => ['accepted'],
            'photos' => ['nullable', 'array', 'max:8'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        $customer = $request->user() ?? $this->directory->findOrCreate(
            $dealer, $data['name'], $data['email'], $data['phone'], 'sell_your_car', $data['postcode']
        );
        $lead = Lead::query()->create([
            'customer_id' => $customer->id,
            'type' => $data['intent'] === 'sell' ? 'sell' : 'part_exchange',
            'subject' => strtoupper($data['registration']).' '.$data['make'].' '.$data['model'],
            'message' => $data['notes'] ?? null,
            'accepted_terms_at' => now(),
            'created_on' => now()->toDateString(),
        ]);
        $valuation = Valuation::query()->create([
            'lead_id' => $lead->id,
            'registration' => $data['registration'],
            'mileage' => $data['mileage'],
            'make' => $data['make'],
            'model' => $data['model'],
            'year' => $data['year'],
            'fuel' => $data['fuel'],
            'transmission' => $data['transmission'],
            'intent' => $data['intent'],
            'condition' => $data['condition'],
            'service_history' => $data['service_history'],
            'mot_expiry' => $data['mot_expiry'] ?? null,
            'outstanding_finance' => $data['outstanding_finance'],
            'notes' => $data['notes'] ?? null,
            'preferred_contact' => $data['preferred_contact'],
            'accepted_terms_at' => now(),
        ]);

        foreach ($request->file('photos', []) as $index => $photo) {
            ValuationPhoto::query()->create([
                'valuation_id' => $valuation->id,
                'storage_key' => $photo->store('valuations', 'local'),
                'sort_order' => $index,
            ]);
        }

        $this->mailer->valuationCustomer($customer, $lead);
        $this->mailer->valuationDealer($dealer, $customer, $lead);

        return redirect()->route('sell')->with('status', 'Thanks. We have your car details and will contact you.');
    }

    public function finance(Request $request)
    {
        $dealer = Dealer::current();
        $car = null;
        if ($request->filled('car')) {
            $car = $dealer->vehicles()->public()->where('slug', $request->string('car'))->first();
        }

        return view('site.finance', compact('dealer', 'car'));
    }

    public function financeEnquiry(Request $request)
    {
        $dealer = Dealer::current();
        $data = $this->contact($request);
        $car = $request->filled('car')
            ? $dealer->vehicles()->public()->where('slug', $request->string('car'))->first()
            : null;
        $customer = $request->user() ?? $this->directory->findOrCreate($dealer, $data['name'], $data['email'], $data['phone'], 'finance_enquiry');
        $lead = Lead::query()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $car?->id,
            'type' => 'finance',
            'subject' => 'Finance enquiry'.($car ? ': '.$car->title() : ''),
            'message' => $data['message'],
            'accepted_terms_at' => now(),
            'created_on' => now()->toDateString(),
        ]);
        $this->mailer->financeCustomer($customer, $lead, $car);
        $this->mailer->financeDealer($dealer, $customer, $lead, $car);

        return back()->with('status', 'Thanks. This is an enquiry only. We will talk you through the options.');
    }

    public function about()
    {
        return view('site.about', ['dealer' => Dealer::current()]);
    }

    public function privacy()
    {
        return view('site.privacy', ['dealer' => Dealer::current()]);
    }

    public function account(Request $request, string $section = 'overview')
    {
        $customer = $request->user();
        $section = $section ?: 'overview';
        $counts = [
            'saved' => $customer->savedVehicles()->count(),
            'bookings' => Booking::query()->where('customer_id', $customer->id)->count(),
            'enquiries' => Lead::query()->where('customer_id', $customer->id)->count(),
            'invoices' => Invoice::query()->where('customer_id', $customer->id)->whereIn('status', ['issued', 'part_paid', 'paid', 'credited', 'void'])->count(),
            'sales' => Sale::query()->where('customer_id', $customer->id)->where('status', 'completed')->count(),
            'alerts' => $this->notifier->unreadCount('customer', $customer->id),
            'open_bookings' => Booking::query()->where('customer_id', $customer->id)->whereIn('status', ['held', 'requested', 'confirmed'])->count(),
        ];

        $payload = [
            'customer' => $customer,
            'section' => $section,
            'counts' => $counts,
            'saved' => null,
            'bookings' => null,
            'leads' => null,
            'invoices' => null,
            'sales' => null,
            'notifications' => null,
            'recentBookings' => collect(),
            'recentLeads' => collect(),
            'recentSaved' => collect(),
        ];

        if ($section === 'overview') {
            $savedIds = $customer->savedVehicles()->latest('id')->limit(4)->pluck('vehicle_id');
            $payload['recentSaved'] = Vehicle::query()
                ->where('dealer_id', $customer->dealer_id)
                ->whereIn('id', $savedIds)
                ->with('images')
                ->get();
            $payload['recentBookings'] = Booking::query()->where('customer_id', $customer->id)->with('vehicle')->latest('id')->limit(5)->get();
            $payload['recentLeads'] = Lead::query()->where('customer_id', $customer->id)->with('vehicle')->latest('id')->limit(5)->get();
            $payload['notifications'] = $this->notifier->recentFor('customer', $customer->id, 5);
        }

        if ($section === 'saved') {
            // Intentionally not scoped with public(): keep sold/hidden cars as "No longer available".
            $savedIds = $customer->savedVehicles()->pluck('vehicle_id');
            $payload['saved'] = Vehicle::query()
                ->where('dealer_id', $customer->dealer_id)
                ->whereIn('id', $savedIds)
                ->with('images')
                ->latest('id')
                ->paginate(12)
                ->withQueryString();
        }

        if ($section === 'bookings') {
            $payload['bookings'] = Booking::query()
                ->where('customer_id', $customer->id)
                ->with('vehicle')
                ->latest('id')
                ->paginate(15)
                ->withQueryString();
        }

        if ($section === 'enquiries') {
            $payload['leads'] = Lead::query()
                ->where('customer_id', $customer->id)
                ->with('vehicle')
                ->latest('id')
                ->paginate(15)
                ->withQueryString();
        }

        if ($section === 'invoices') {
            $payload['invoices'] = Invoice::query()
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['issued', 'part_paid', 'paid', 'credited', 'void'])
                ->latest('id')
                ->paginate(15)
                ->withQueryString();
            $payload['sales'] = Sale::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'completed')
                ->with('vehicle')
                ->latest('id')
                ->paginate(10, ['*'], 'sales_page')
                ->withQueryString();
        }

        if ($section === 'alerts') {
            $payload['notifications'] = AppNotification::query()
                ->where('notifiable_type', 'customer')
                ->where('notifiable_id', $customer->id)
                ->latest('id')
                ->paginate(20)
                ->withQueryString();
        }

        return view('site.account', $payload);
    }

    public function updateProfile(Request $request)
    {
        $customer = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            'postcode' => ['nullable', 'string', 'max:16'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
        $customer->fill($data);
        $customer->save();

        return redirect()->route('account', 'profile')->with('status', 'Profile saved.');
    }

    public function updatePassword(Request $request)
    {
        $customer = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        if (! Hash::check($data['current_password'], $customer->password_hash)) {
            throw ValidationException::withMessages(['current_password' => 'Current password is incorrect.']);
        }
        $customer->password_hash = $data['password'];
        $customer->save();

        return redirect()->route('account', 'profile')->with('status', 'Password updated.');
    }

    public function unsave(Request $request, Vehicle $vehicle)
    {
        $customer = $request->user();
        $this->directory->removeSaved($customer, $vehicle->id);

        return redirect()->route('account', 'saved')->with('status', 'Removed from saved cars.');
    }

    public function sitemap()
    {
        $dealer = Dealer::current();
        $cars = $dealer->vehicles()->public()->get(['slug', 'updated_at']);

        return response()->view('site.sitemap', compact('cars'))->header('Content-Type', 'application/xml');
    }

    public function photo(Request $request, ValuationPhoto $photo)
    {
        $staff = $request->user('staff');
        abort_unless($staff && (int) $photo->valuation->lead->customer->dealer_id === (int) $staff->dealer_id, 403);

        return Storage::disk('local')->download($photo->storage_key);
    }

    private function searchChoices($public): array
    {
        $prices = (clone $public)
            ->selectRaw('COALESCE(total_price_pence, supplied_price_pence, cash_price_pence) as pence')
            ->pluck('pence')
            ->map(fn ($pence) => (int) ceil(((int) $pence) / 100));

        return [
            'makes' => (clone $public)->whereNotNull('make')->distinct()->orderBy('make')->pluck('make'),
            'makeCounts' => (clone $public)->whereNotNull('make')->selectRaw('make, COUNT(*) as aggregate')->groupBy('make')->pluck('aggregate', 'make'),
            'modelRows' => (clone $public)->select('make', 'model')->whereNotNull('make')->whereNotNull('model')->distinct()->orderBy('make')->orderBy('model')->get(),
            'prices' => $this->steps($prices, 1000),
            'years' => (clone $public)->whereNotNull('year')->distinct()->orderByDesc('year')->pluck('year'),
            'mileages' => $this->steps((clone $public)->whereNotNull('mileage')->pluck('mileage'), 20000),
            'fuels' => (clone $public)->whereNotNull('fuel')->distinct()->orderBy('fuel')->pluck('fuel'),
            'transmissions' => (clone $public)->whereNotNull('transmission')->distinct()->orderBy('transmission')->pluck('transmission'),
            'bodies' => (clone $public)->whereNotNull('body')->distinct()->orderBy('body')->pluck('body'),
        ];
    }

    private function steps($values, int $step): array
    {
        $values = collect($values)->map(fn ($value) => (int) $value)->filter(fn ($value) => $value > 0);
        if ($values->isEmpty()) {
            return [];
        }
        if ($step === 1000 && $values->max() > 20000) {
            $step = 5000;
        }
        $min = (int) $values->min();
        $max = (int) $values->max();
        $bands = [];
        $ceiling = (int) (ceil($max / $step) * $step);
        for ($band = (int) (ceil($min / $step) * $step); $band <= $ceiling; $band += $step) {
            $bands[] = $band;
        }

        return $bands;
    }

    private function filtered(Request $request, Dealer $dealer)
    {
        $query = $dealer->vehicles()->public()->with('images');
        if ($request->filled('q')) {
            $term = '%'.addcslashes((string) $request->string('q'), '%_\\').'%';
            $query->where(function ($inner) use ($term) {
                $inner->where('make', 'like', $term)
                    ->orWhere('model', 'like', $term)
                    ->orWhere('registration', 'like', $term)
                    ->orWhere('trim', 'like', $term)
                    ->orWhere('colour', 'like', $term);
            });
        }
        if ($request->filled('make')) {
            $query->where('make', $request->string('make'));
        }
        if ($request->filled('model')) {
            $query->where('model', $request->string('model'));
        }
        if ($request->filled('price')) {
            $query->whereRaw('COALESCE(total_price_pence, supplied_price_pence, cash_price_pence) <= ?', [(int) $request->input('price') * 100]);
        }
        if ($request->filled('year')) {
            $query->where('year', '>=', (int) $request->input('year'));
        }
        if ($request->filled('mileage')) {
            $query->where('mileage', '<=', (int) $request->input('mileage'));
        }
        foreach (['fuel', 'transmission', 'body'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->string($field));
            }
        }
        if ($request->boolean('saved')) {
            $ids = $this->directory->savedIds($request->user());
            $query->whereIn('id', $ids === [] ? [0] : $ids);
        }

        return match ($request->input('sort', 'featured')) {
            'price_asc' => $query->orderByRaw('COALESCE(total_price_pence, supplied_price_pence, cash_price_pence) asc'),
            'price_desc' => $query->orderByRaw('COALESCE(total_price_pence, supplied_price_pence, cash_price_pence) desc'),
            'newest' => $query->orderByDesc('year'),
            'mileage' => $query->orderBy('mileage'),
            default => $query->orderByDesc('featured')->orderByRaw('attention_grabber is null')->orderByDesc('id'),
        };
    }

    private function bookAppointment(Request $request, string $slug, string $type)
    {
        $dealer = Dealer::current();
        $car = $dealer->vehicles()->public()->where('slug', $slug)->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email'],
            'phone' => ['required', 'string', 'max:40'],
            'preferred_on' => ['required', 'date', 'after_or_equal:today'],
            'preferred_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'accepted_terms' => ['accepted'],
        ]);
        $customer = $type === 'viewing'
            ? $request->user()
            : ($request->user() ?? $this->directory->findOrCreate($dealer, $data['name'], $data['email'], $data['phone'], 'website_enquiry'));
        try {
            $booking = $this->bookings->appointment($car, $customer, $type, $data['preferred_on'], $data['preferred_time'], $data['notes'] ?? null);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'preferred_time' => 'That time is already booked.',
            ]);
        }

        $this->mailer->appointmentCustomer($customer, $booking, $car);
        $this->mailer->appointmentDealer($dealer, $customer, $booking, $car);
        $label = $type === 'viewing' ? 'viewing' : 'test drive';
        $this->notifier->notifyStaff($dealer, 'booking.new', 'New '.$label.': '.$car->title(), $customer->name, route('dealer.bookings'));
        $this->notifier->notifyCustomer($customer, 'booking.new', 'Your '.$label.' request: '.$car->title(), null, route('account'));

        return back()->with('status', 'Your '.$label.' request has been received.');
    }

    private function contact(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email'],
            'phone' => ['required', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:5000'],
            'accepted_terms' => ['accepted'],
        ]);
    }
}
