@extends('layouts.site')
@section('title', $car->title())
@section('description', $car->title().' for sale at Ghani Motors, Dagenham.')
@section('content')
<section class="section">
    <div class="crumb"><a href="{{ route('stock') }}">← Back to all cars</a> / {{ $car->make }} {{ $car->model }}</div>
    <div class="detail">
        <div class="gallery">
            @if ($car->images->first())
                <img src="{{ $car->images->first()->url }}" alt="{{ $car->title() }}">
            @endif
            @if ($car->images->count() > 1)
                <div class="specs" style="margin-top:10px;">
                    @foreach ($car->images as $image)
                        <img src="{{ $image->url }}" alt="" style="width:88px; height:64px; object-fit:cover; border-radius:5px;">
                    @endforeach
                </div>
            @endif
            <h2>The details that matter.</h2>
            <div class="keyfacts">
                @foreach (['Year' => $car->year, 'Mileage' => $car->mileage ? number_format($car->mileage).' miles' : null, 'Fuel' => $car->fuel, 'Transmission' => $car->transmission, 'Body type' => $car->body, 'Colour' => $car->colour, 'Owners' => $car->owners, 'Previous owners' => $car->history_previous_owners, 'Registration' => $car->registration] as $label => $value)
                    <div><small>{{ $label }}</small><b>{{ $value ?: '—' }}</b></div>
                @endforeach
            </div>
            @if ($car->highlights->isNotEmpty() || $car->features->isNotEmpty())
                <h3>Vehicle highlights</h3>
                <div class="features">
                    @foreach ($car->highlights as $highlight)<span>✓ {{ $highlight->name }}</span>@endforeach
                    @foreach ($car->features as $feature)<span>✓ {{ $feature->name }}</span>@endforeach
                </div>
            @endif
            @if ($car->description)
                <p class="sub" style="white-space:pre-wrap; margin-top:18px;">{{ $car->description }}</p>
            @endif
            @include('partials.trust')
        </div>
        <aside class="panel booking-actions">
            <span class="badge {{ $car->website_status }}">{{ ucfirst($car->website_status) }}</span>
            <h1>{{ $car->title() }}</h1>
            <div class="bigprice">@gbp($car->publicPricePence())</div>
            <div class="detailapproval">
                @include('partials.aa-badge')
                <span>Buy from an AA Approved Dealer.</span>
            </div>
            <p>{{ $car->attention_grabber ?: 'Explore the car in person. Our team is here to answer your questions.' }}</p>
            <form method="post" action="{{ route('cars.save', $car->slug) }}">
                @csrf
                <button class="btn outline" type="submit">{{ $saved ? 'Saved' : 'Save this car' }}</button>
            </form>
            @if ($car->website_status === 'available')
                <details>
                    <summary class="btn">Reserve this car ↗</summary>
                    <form method="post" action="{{ route('cars.reserve', $car->slug) }}">
                        @csrf
                        @include('partials.contact-fields')
                        <label>Preferred date<input type="date" name="preferred_on" required value="{{ old('preferred_on') }}"></label>
                        <label>Notes<textarea name="notes">{{ old('notes') }}</textarea></label>
                        <button class="btn" type="submit">Send reservation</button>
                    </form>
                </details>
            @endif
            <details>
                <summary class="btn outline">Book a viewing</summary>
                <form method="post" action="{{ route('cars.viewing', $car->slug) }}">
                    @csrf
                    @include('partials.contact-fields')
                    <label>Date<input type="date" name="preferred_on" required value="{{ old('preferred_on') }}"></label>
                    <label>Time<input type="time" name="preferred_time" required value="{{ old('preferred_time') }}"></label>
                    <button class="btn" type="submit">Request viewing</button>
                </form>
            </details>
            <details>
                <summary class="btn outline">Request a test drive</summary>
                <form method="post" action="{{ route('cars.test-drive', $car->slug) }}">
                    @csrf
                    @include('partials.contact-fields')
                    <label>Date<input type="date" name="preferred_on" required value="{{ old('preferred_on') }}"></label>
                    <label>Time<input type="time" name="preferred_time" required value="{{ old('preferred_time') }}"></label>
                    <button class="btn" type="submit">Request test drive</button>
                </form>
            </details>
            <details>
                <summary class="btn outline">Ask about this car</summary>
                <form method="post" action="{{ route('cars.enquiry', $car->slug) }}">
                    @csrf
                    @include('partials.contact-fields')
                    <label>Message<textarea name="message">{{ old('message') }}</textarea></label>
                    <button class="btn" type="submit">Send enquiry</button>
                </form>
            </details>
            <a class="btn outline" href="{{ route('finance', ['car' => $car->slug]) }}">Explore finance options</a>
            <div class="hint">Have a car to part exchange? <a href="{{ route('sell') }}"><b>Tell us about it →</b></a></div>
        </aside>
    </div>
</section>
@endsection
