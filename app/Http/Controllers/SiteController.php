<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Dealer;
use App\Models\Lead;
use App\Models\Valuation;
use App\Models\ValuationPhoto;
use App\Models\Vehicle;
use App\Services\BookingService;
use App\Services\CustomerDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SiteController extends Controller
{
    public function __construct(
        private CustomerDirectory $directory,
        private BookingService $bookings,
    ) {}

    public function home(Request $request)
    {
        $dealer = Dealer::current();
        $request->query->remove('fragment');
        $cars = $this->filtered($request, $dealer)->paginate(12)->withQueryString();
        $makes = $dealer->vehicles()->public()->whereNotNull('make')->distinct()->orderBy('make')->pluck('make');
        $modelRows = $dealer->vehicles()->public()->select('make', 'model')->whereNotNull('model')->distinct()->orderBy('model')->get();

        return view('site.home', compact('dealer', 'cars', 'makes', 'modelRows'));
    }

    public function stock(Request $request)
    {
        $dealer = Dealer::current();
        $fragment = $request->boolean('fragment');
        $request->query->remove('fragment');
        $public = $dealer->vehicles()->public();
        $makes = (clone $public)->whereNotNull('make')->distinct()->orderBy('make')->pluck('make');
        $modelRows = (clone $public)->select('make', 'model')->whereNotNull('model')->distinct()->orderBy('model')->get();

        $cars = $this->filtered($request, $dealer)->paginate(12)->withQueryString();
        if ($fragment) {
            return view('partials.stock-results', compact('cars'));
        }
        $saved = $this->directory->savedIds($request->user());

        return view('site.stock', [
            'dealer' => $dealer,
            'cars' => $cars,
            'makes' => $makes,
            'modelRows' => $modelRows,
            'saved' => $saved,
            'fuels' => (clone $public)->whereNotNull('fuel')->distinct()->orderBy('fuel')->pluck('fuel'),
            'transmissions' => (clone $public)->whereNotNull('transmission')->distinct()->orderBy('transmission')->pluck('transmission'),
            'bodies' => (clone $public)->whereNotNull('body')->distinct()->orderBy('body')->pluck('body'),
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $dealer = Dealer::current();
        $car = $dealer->vehicles()->public()->where('slug', $slug)->with(['images', 'features', 'highlights'])->firstOrFail();
        $saved = in_array($car->id, $this->directory->savedIds($request->user()), true);

        return view('site.car', compact('dealer', 'car', 'saved'));
    }

    public function save(Request $request, string $slug)
    {
        $dealer = Dealer::current();
        $car = $dealer->vehicles()->public()->where('slug', $slug)->firstOrFail();
        $this->directory->toggleSaved($dealer, $car->id, $request->user());

        return back();
    }

    public function enquiry(Request $request, string $slug)
    {
        $dealer = Dealer::current();
        $car = $dealer->vehicles()->public()->where('slug', $slug)->firstOrFail();
        $data = $this->contact($request);
        $customer = $request->user() ?? $this->directory->findOrCreate($dealer, $data['name'], $data['email'], $data['phone'], 'website_enquiry');
        Lead::query()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $car->id,
            'type' => 'general',
            'subject' => 'Enquiry: '.$car->title(),
            'message' => $data['message'],
            'accepted_terms_at' => now(),
            'created_on' => now()->toDateString(),
        ]);

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
        $customer = $request->user() ?? $this->directory->findOrCreate($dealer, $data['name'], $data['email'], $data['phone'], 'website_enquiry');
        $this->bookings->reserve($car, $customer, $data['preferred_on'], $data['notes'] ?? null);

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
        Lead::query()->create([
            'customer_id' => $customer->id,
            'type' => 'general',
            'subject' => 'Website enquiry',
            'message' => $data['message'],
            'accepted_terms_at' => now(),
            'created_on' => now()->toDateString(),
        ]);

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
        Lead::query()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $car?->id,
            'type' => 'finance',
            'subject' => 'Finance enquiry'.($car ? ': '.$car->title() : ''),
            'message' => $data['message'],
            'accepted_terms_at' => now(),
            'created_on' => now()->toDateString(),
        ]);

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

    public function account(Request $request)
    {
        $customer = $request->user();
        $saved = Vehicle::query()->public()->whereIn('id', $customer->savedVehicles()->pluck('vehicle_id'))->with('images')->get();
        $bookings = Booking::query()->where('customer_id', $customer->id)->with('vehicle')->latest()->get();
        $leads = Lead::query()->where('customer_id', $customer->id)->latest()->get();

        return view('site.account', compact('customer', 'saved', 'bookings', 'leads'));
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
            default => $query->orderByRaw('attention_grabber is null')->orderByDesc('id'),
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
        $customer = $request->user() ?? $this->directory->findOrCreate($dealer, $data['name'], $data['email'], $data['phone'], 'website_enquiry');
        try {
            $this->bookings->appointment($car, $customer, $type, $data['preferred_on'], $data['preferred_time'], $data['notes'] ?? null);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'preferred_time' => 'That time is already booked.',
            ]);
        }

        $label = $type === 'viewing' ? 'viewing' : 'test drive';

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
