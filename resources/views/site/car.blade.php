@extends('layouts.site')
@php
    $pageOgImage = $car->images->first()?->sized('w800h600') ?: ($dealer->logoUrl() ?: asset('images/ghani-mail-logo.png'));
    $pageDescription = $car->title().' for sale at '.($dealer->displayName() ?? 'Ghani Motors').', '.($dealer->city ?? 'Dagenham').'.';
@endphp
@section('title', $car->title())
@section('description', $pageDescription)
@section('og_image', $pageOgImage)
@section('content')
@php
    $price = $car->publicPricePence();
    $pounds = $price !== null ? intdiv($price, 100) : null;
    $plate = strtoupper(preg_replace('/\s+/', '', (string) $car->registration) ?? '');
    if (strlen($plate) === 7) {
        $plate = substr($plate, 0, 4).' '.substr($plate, 4);
    }
    $bodyNote = collect([$car->doors ? $car->doors.' doors' : null, $car->seats ? $car->seats.' seats' : null])->filter()->implode(' / ');
    $standard = $car->features->where('kind', 'standard')->values();
    $optional = $car->features->where('kind', 'optional')->values();
    $featureTotal = $car->highlights->count() + $car->features->count();
    $phone = $dealer->whatsappNumber();
    $ogImage = $car->images->first()?->sized('w800h600');
@endphp
<script type="application/ld+json">
{!! json_encode(array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'Car',
    'name' => $car->title(),
    'brand' => $car->make,
    'model' => $car->model,
    'vehicleModelDate' => $car->year,
    'fuelType' => $car->fuel,
    'vehicleTransmission' => $car->transmission,
    'color' => $car->colour,
    'image' => $ogImage,
    'offers' => $price ? ['@type' => 'Offer', 'priceCurrency' => 'GBP', 'price' => number_format($price / 100, 2, '.', ''), 'availability' => 'https://schema.org/InStock'] : null,
    'seller' => ['@type' => 'AutoDealer', 'name' => $dealer->displayName(), 'address' => $dealer->showroomAddress()],
]), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}
</script>
<div class="car-page">
    <nav class="car-crumb" aria-label="Breadcrumb">
        <a href="{{ route('stock') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 19l-7-7m0 0l7-7m-7 7h18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            Back to all cars
        </a>
        <span>/</span>
        <b>{{ $car->make }} {{ $car->model }}</b>
    </nav>

    <section class="car-card car-hero">
        <div>
            <div class="car-pills">
                <span class="car-status {{ $car->website_status }}">{{ ucfirst($car->website_status) }}</span>
                @if ($plate !== '')<span class="car-ref">Ref: {{ $plate }}</span>@endif
            </div>
            <h1>{{ $car->title() }}</h1>
            @if ($car->attention_grabber)
                <p class="car-lead">{{ $car->attention_grabber }}</p>
            @endif
            <div class="car-pills">
                @if ($car->owners)
                    <span>{{ $car->owners === 1 ? '1 owner' : $car->owners.' owners' }}</span>
                @endif
                @if ($car->mileage)
                    <span>{{ number_format($car->mileage) }} miles</span>
                @endif
                @foreach ($car->highlights->take(3) as $highlight)
                    <span>{{ $highlight->name }}</span>
                @endforeach
            </div>
        </div>
        <div class="car-pricebox">
            <small>Total vehicle price</small>
            <strong>@gbp($price)</strong>
            @if ($pounds)
                <a href="#finance-calculator">See an illustrative monthly figure →</a>
            @endif
        </div>
    </section>

    <div class="car-layout">
        <div class="car-main">
            <section class="car-card car-gallery" data-gallery aria-label="Vehicle photos">
                <div class="car-stage">
                    @if ($image = $car->images->first())
                        <img data-gallery-main src="{{ $image->sized('w800h600') }}" alt="{{ $car->title() }}">
                        <div class="car-stage-badges">
                            <span>{{ $car->images->count() }} {{ \Illuminate\Support\Str::plural('photo', $car->images->count()) }}</span>
                            @if ($car->video_url)
                                <a href="{{ $car->video_url }}" target="_blank" rel="noopener">Walkaround video</a>
                            @endif
                        </div>
                        <button type="button" data-gallery-open aria-label="Enlarge image">Enlarge</button>
                    @else
                        <div class="car-stage-empty">Photos for this car will appear here.</div>
                    @endif
                </div>
                @if ($car->images->count() > 1)
                    <div class="car-thumbs">
                        @foreach ($car->images as $thumb)
                            <button type="button" data-gallery-thumb data-full="{{ $thumb->sized('w800h600') }}" data-alt="{{ $car->title() }}" @class(['is-selected' => $loop->first])>
                                <img src="{{ $thumb->sized('w400h300') }}" alt="">
                            </button>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="car-card">
                <div class="car-section-head">
                    <div>
                        <h2>The details that matter.</h2>
                        <p>Key mechanical and physical specifications</p>
                    </div>
                </div>
                <div class="car-specs">
                    @foreach ([
                        ['Year', $car->year, $car->first_registration_on?->format('j M Y')],
                        ['Mileage', $car->mileage ? number_format($car->mileage) : null, $car->mileage ? 'miles' : null],
                        ['Fuel', $car->fuel, null],
                        ['Gearbox', $car->transmission, null],
                        ['Body type', $car->body, $bodyNote ?: null],
                        ['Colour', $car->colour, null],
                        ['Keepers', $car->owners ? ($car->owners === 1 ? '1 owner' : $car->owners.' owners') : null, $car->history_previous_owners !== null ? $car->history_previous_owners.' previous' : null],
                        ['Engine', $car->engine_cc ? number_format($car->engine_cc).' cc' : null, null],
                        ['Registration', $plate !== '' ? $plate : null, null],
                    ] as [$label, $value, $note])
                        @continue($value === null || $value === '')
                        <div>
                            <small>{{ $label }}</small>
                            <b>{{ $value }}</b>
                            @if ($note)<span>{{ $note }}</span>@endif
                        </div>
                    @endforeach
                </div>
            </section>

            @if ($car->description || $car->description_2)
                <section class="car-card">
                    <h2>About this car</h2>
                    @if ($car->description)<p class="car-copy">{{ $car->description }}</p>@endif
                    @if ($car->description_2)<p class="car-copy">{{ $car->description_2 }}</p>@endif
                </section>
            @endif

            @if ($featureTotal > 0)
                <section class="car-card">
                    <div class="car-section-head">
                        <div>
                            <h2>Vehicle features and equipment</h2>
                            <p>{{ $featureTotal }} {{ \Illuminate\Support\Str::plural('item', $featureTotal) }} listed for this car</p>
                        </div>
                        <label class="car-search">
                            <span class="sr">Search equipment</span>
                            <input data-feature-search type="search" placeholder="Search equipment">
                        </label>
                    </div>
                    @if ($car->highlights->isNotEmpty())
                        <div class="car-feature-group" data-feature-group>
                            <header>Highlights</header>
                            <div>
                                @foreach ($car->highlights as $highlight)
                                    <p class="car-tick" data-feature="{{ strtolower($highlight->name) }}">{{ $highlight->name }}</p>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($optional->isNotEmpty())
                        <div class="car-feature-group" data-feature-group>
                            <header>Optional equipment</header>
                            <div>
                                @foreach ($optional as $feature)
                                    <p class="car-tick" data-feature="{{ strtolower($feature->name) }}">{{ $feature->name }}</p>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($standard->isNotEmpty())
                        <div class="car-feature-group" data-feature-group>
                            <header>Standard equipment</header>
                            <div>
                                @foreach ($standard->take(8) as $feature)
                                    <p class="car-tick" data-feature="{{ strtolower($feature->name) }}">{{ $feature->name }}</p>
                                @endforeach
                                @if ($standard->count() > 8)
                                    <div data-features-extra hidden>
                                        @foreach ($standard->slice(8) as $feature)
                                            <p class="car-tick" data-feature="{{ strtolower($feature->name) }}">{{ $feature->name }}</p>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                        @if ($standard->count() > 8)
                            <button class="car-more" type="button" data-features-more>View all {{ $standard->count() }} standard features</button>
                        @endif
                    @endif
                </section>
            @endif

            @if ($car->chargeTimes->isNotEmpty())
                <section class="car-card">
                    <h2>Charge times</h2>
                    <div class="car-specs">
                        @foreach ($car->chargeTimes as $time)
                            <div>
                                <small>{{ $time->charger_type }}</small>
                                <b>{{ $time->duration_minutes ? $time->duration_minutes.' min' : '—' }}</b>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($pounds)
                <section class="car-card" id="finance-calculator" data-calculator>
                    <div class="car-section-head">
                        <div>
                            <h2>Illustrative finance example</h2>
                            <p>Adjust the deposit and term. This is not a finance offer.</p>
                        </div>
                    </div>
                    <div class="car-finance">
                        <div>
                            <input type="hidden" name="price" value="{{ $pounds }}">
                            <label>Customer deposit <b data-deposit-label></b>
                                <input type="range" name="deposit" min="0" max="{{ $pounds }}" step="50" value="{{ min(2500, $pounds) }}">
                            </label>
                            <label>Agreement term <b data-months-label>48 months</b>
                                <input type="range" name="months" min="24" max="60" step="12" value="48">
                            </label>
                        </div>
                        <div class="car-finance-result">
                            <small>Illustrative monthly payment</small>
                            <strong data-payment>—</strong>
                            <span>Assumed 12.9% annual rate</span>
                            <a class="car-btn primary" href="{{ route('finance', ['car' => $car->slug]) }}">Ask about finance</a>
                            <p>This is an illustration, not a quote or a credit check.</p>
                        </div>
                    </div>
                </section>
            @endif
        </div>

        <aside class="car-side">
            <div class="car-card car-buy">
                <small>Total online price</small>
                <strong>@gbp($price)</strong>
                <p>Ready to view at the Dagenham showroom.</p>
                @php
                    $carPath = route('cars.show', $car->slug, false);
                    $loginToCar = route('login', ['redirect' => $carPath]);
                    $loginToReserve = route('login', ['redirect' => $carPath.'#reserve']);
                    $loginToViewing = route('login', ['redirect' => $carPath.'#viewing']);
                @endphp
                <div class="car-actions">
                    @auth
                        <form method="post" action="{{ route('cars.save', $car->slug) }}">
                            @csrf
                            <button class="car-btn" type="submit" data-save="{{ $car->id }}" aria-label="{{ $saved ? 'Unsave' : 'Save' }} {{ $car->title() }}">{{ $saved ? 'Saved' : 'Save this car' }}</button>
                        </form>
                    @else
                        <a class="car-btn" href="{{ $loginToCar }}">Log in to save</a>
                    @endauth
                    @if ($car->website_status === 'available')
                        @auth
                            <details id="reserve" @if($errors->any()) open @endif>
                                <summary class="car-btn primary">Reserve this car</summary>
                                <form method="post" action="{{ route('cars.reserve', $car->slug) }}">
                                    @csrf
                                    @include('partials.contact-fields')
                                    <label>Preferred date<input type="date" name="preferred_on" required value="{{ old('preferred_on') }}"></label>
                                    <label>Notes<textarea name="notes">{{ old('notes') }}</textarea></label>
                                    <button class="car-btn primary" type="submit">Send reservation</button>
                                </form>
                            </details>
                        @else
                            <a class="car-btn primary" id="reserve" href="{{ $loginToReserve }}">Log in to reserve</a>
                        @endauth
                    @endif
                    @auth
                        <details id="viewing" @if($errors->any()) open @endif>
                            <summary class="car-btn">Book a viewing</summary>
                            <form method="post" action="{{ route('cars.viewing', $car->slug) }}">
                                @csrf
                                @include('partials.contact-fields')
                                <label>Date<input type="date" name="preferred_on" required value="{{ old('preferred_on') }}"></label>
                                <label>Time<input type="time" name="preferred_time" required value="{{ old('preferred_time') }}"></label>
                                <button class="car-btn primary" type="submit">Request viewing</button>
                            </form>
                        </details>
                    @else
                        <a class="car-btn" id="viewing" href="{{ $loginToViewing }}">Log in to book a viewing</a>
                    @endauth
                    <details>
                        <summary class="car-btn">Request a test drive</summary>
                        <form method="post" action="{{ route('cars.test-drive', $car->slug) }}">
                            @csrf
                            @include('partials.contact-fields')
                            <label>Date<input type="date" name="preferred_on" required value="{{ old('preferred_on') }}"></label>
                            <label>Time<input type="time" name="preferred_time" required value="{{ old('preferred_time') }}"></label>
                            <button class="car-btn primary" type="submit">Request test drive</button>
                        </form>
                    </details>
                    <details>
                        <summary class="car-btn">Ask about this car</summary>
                        <form method="post" action="{{ route('cars.enquiry', $car->slug) }}">
                            @csrf
                            @include('partials.contact-fields')
                            <label>Message<textarea name="message">{{ old('message') }}</textarea></label>
                            <button class="car-btn primary" type="submit">Send enquiry</button>
                        </form>
                    </details>
                </div>
                <a class="car-exchange" href="{{ route('sell') }}">
                    <b>Have a car to part exchange?</b>
                    <span>Tell us about it and we can talk it through against this car.</span>
                </a>
                <div class="car-promises">
                    <h3>Included with this vehicle</h3>
                    <p>AA Approved Dealer</p>
                    <p>HPI checked</p>
                    <p>3-month warranty</p>
                    <p>12-month breakdown cover</p>
                    <p>5-day drive-away insurance</p>
                </div>
            </div>
            <div class="car-card car-visit">
                <h3>Visit the showroom</h3>
                <p><b>Ghani Motors</b></p>
                <p>{{ $dealer->address_line }}, {{ $dealer->city }}, {{ $dealer->postcode }}</p>
                @if ($phone)
                    <a href="tel:+{{ $phone }}">Call {{ $dealer->phone }}</a>
                @endif
            </div>
        </aside>
    </div>
</div>
<div class="car-mobile-bar">
    <div>
        <small>{{ $pounds ? 'Total price' : 'Enquire' }}</small>
        <b>
            @if ($pounds)
                @gbp($price)
            @else
                Ask for price
            @endif
        </b>
    </div>
    <div>
        @if ($phone)
            <a href="tel:+{{ $phone }}" aria-label="Call the showroom">Call</a>
        @endif
        @if ($car->website_status === 'available' && $pounds)
            @auth
                <button type="button" data-open-reserve>Reserve</button>
            @else
                <a href="{{ $loginToReserve }}">Reserve</a>
            @endauth
        @else
            <button type="button" data-enquiry-open>Enquire</button>
        @endif
    </div>
</div>
<dialog class="car-lightbox" data-lightbox>
    <button type="button" data-lightbox-close>Close</button>
    <img alt="" data-lightbox-image>
</dialog>
@endsection
