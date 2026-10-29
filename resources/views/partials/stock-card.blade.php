@php
    $price = $car->publicPricePence();
    $pounds = $price !== null ? intdiv($price, 100) : null;
    $monthly = null;
    if ($pounds !== null) {
        $rate = 0.129 / 12;
        $payment = ($pounds * 0.9) * $rate / (1 - pow(1 + $rate, -48));
        $monthly = is_finite($payment) ? (int) round($payment) : null;
    }
    $plate = strtoupper(preg_replace('/\s+/', '', (string) $car->registration) ?? '');
    if (strlen($plate) === 7) {
        $plate = substr($plate, 0, 4).' '.substr($plate, 4);
    }
    $gear = (string) $car->transmission;
    if (str_contains(strtolower($gear), 'automatic')) {
        $gear = 'Auto';
    }
    $image = $car->images->first();
    $photos = $car->images->count();
    $milesFull = $car->mileage !== null ? number_format((int) $car->mileage) : '—';
    $milesShort = $car->mileage !== null
        ? (abs((int) $car->mileage) >= 1000
            ? number_format((int) round(((int) $car->mileage) / 1000)).'k'
            : number_format((int) $car->mileage))
        : '—';
@endphp
<article @class(['stock-card', 'is-featured' => $car->featured])>
    <div>
        <div class="stock-media-wrap">
        <a class="stock-media" href="{{ route('cars.show', $car->slug) }}" aria-label="View {{ $car->title() }}">
            @if ($image)
                <img src="{{ $image->sized('w800h600') }}" alt="{{ $car->title() }}">
            @else
                <span class="stock-media-empty">Photo to follow</span>
            @endif
            <span class="stock-badges">
                <span class="stock-status {{ $car->website_status }}">{{ ucfirst($car->website_status) }}</span>
                @if ($car->featured)
                    <span class="stock-featured">Featured</span>
                @endif
            </span>
            <span class="stock-media-meta">
                @if ($plate !== '')
                    <span class="stock-reg-pill">Reg {{ $plate }}</span>
                @endif
                @if ($photos > 0)
                    <span class="stock-photos">1 / {{ $photos }}</span>
                @endif
            </span>
        </a>
        @auth
            <form method="post" action="{{ route('cars.save', $car->slug) }}">
                @csrf
                <button type="submit" data-save="{{ $car->id }}" aria-label="Save {{ $car->title() }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-7-4.4-9.2-8.2C1 8.8 2.2 5.6 5.4 4.8 7.4 4.3 9.3 5.2 12 8c2.7-2.8 4.6-3.7 6.6-3.2 3.2.8 4.4 4 2.6 7-2.2 3.8-9.2 8.2-9.2 8.2z"></path></svg>
                </button>
            </form>
        @else
            <a class="stock-save-login" href="{{ route('login', ['redirect' => route('cars.show', $car->slug, false)]) }}" aria-label="Log in to save {{ $car->title() }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-7-4.4-9.2-8.2C1 8.8 2.2 5.6 5.4 4.8 7.4 4.3 9.3 5.2 12 8c2.7-2.8 4.6-3.7 6.6-3.2 3.2.8 4.4 4 2.6 7-2.2 3.8-9.2 8.2-9.2 8.2z" fill="none" stroke="currentColor" stroke-width="1.8"></path></svg>
            </a>
        @endauth
        </div>
        <div class="stock-copy">
            <div class="stock-regline">
                @if ($plate !== '')<span>Reg {{ $plate }}</span>@endif
            </div>
            <h3><a href="{{ route('cars.show', $car->slug) }}">{{ $car->make }} {{ $car->model }}</a></h3>
            <p>{{ $car->trim ?: $car->attention_grabber }}</p>
            <dl>
                <div><dt>Year</dt><dd>{{ $car->year ?: '—' }}</dd></div>
                <div><dt>Mileage</dt><dd><span class="stock-miles-full">{{ $milesFull }}</span><span class="stock-miles-short">{{ $milesShort }}</span></dd></div>
                <div><dt>Gearbox</dt><dd>{{ $gear !== '' ? $gear : '—' }}</dd></div>
                <div><dt>Fuel</dt><dd>{{ $car->fuel ?: '—' }}</dd></div>
            </dl>
        </div>
    </div>
    <div class="stock-buy">
        <div class="stock-prices">
            <div>
                <small>Cash price</small>
                <strong>@gbp($price)</strong>
            </div>
            @if ($monthly)
                <div>
                    <b>£{{ number_format($monthly) }} <span class="stock-mo">/mo</span></b>
                    <small title="Assumed 12.9% APR over 48 months with a 10% deposit. An illustration, not a finance quote.">Illustration · 10% dep</small>
                </div>
            @endif
        </div>
        <div class="stock-actions">
            @auth
                <a class="stock-reserve" href="{{ route('cars.show', $car->slug) }}#reserve">Reserve</a>
            @else
                <a class="stock-reserve" href="{{ route('login', ['redirect' => route('cars.show', $car->slug, false).'#reserve']) }}">Reserve</a>
            @endauth
            <a class="stock-view" href="{{ route('cars.show', $car->slug) }}">View vehicle</a>
        </div>
    </div>
</article>
