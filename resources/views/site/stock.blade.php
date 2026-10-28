@extends('layouts.site')
@section('title', 'Stock')
@section('description', 'Search used cars at Ghani Motors in Dagenham.')
@section('content')
@php
    $priceCeiling = (int) (collect($prices)->last() ?? 0);
    $priceFloor = $priceCeiling > 0 ? min((int) (collect($prices)->first() ?? $priceCeiling), $priceCeiling) : 0;
    $chosenPrice = request()->filled('price') ? (int) request('price') : $priceCeiling;
    $mileageOptions = collect($mileages)->map(fn ($miles) => (int) $miles);
    if (request()->filled('mileage') && ! $mileageOptions->contains((int) request('mileage'))) {
        $mileageOptions->push((int) request('mileage'));
    }
    $mileageOptions = $mileageOptions->unique()->sort()->values();
    $phone = $dealer->whatsappNumber();
    $address = trim(implode(', ', array_filter([$dealer->address_line, $dealer->city, $dealer->postcode])));
@endphp
<div class="stock-page">
    <nav class="stock-crumb" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 19l-7-7m0 0l7-7m-7 7h18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            Home
        </a>
        <span>/</span>
        <b>Used cars</b>
    </nav>

    <section class="stock-hero">
        <div class="stock-kicker"><i></i> Ghani Motors forecourt</div>
        <h1>Find a car that fits your life.</h1>
        <p>Your budget. Your priorities. Your next set of keys. Search the cars in stock at our Dagenham showroom.</p>
        <div class="stock-chips">
            <span>Popular</span>
            @if ($transmissions->contains('Automatic') && $bodies->contains('Hatchback'))
                <a href="{{ route('stock', ['transmission' => 'Automatic', 'body' => 'Hatchback']) }}">Automatic hatchbacks</a>
            @endif
            @if ($priceCeiling >= 5000)
                <a href="{{ route('stock', ['price' => 5000]) }}">Under £5,000</a>
            @endif
            @if ($mileageOptions->isNotEmpty())
                <a href="{{ route('stock', ['mileage' => 60000]) }}">Under 60,000 miles</a>
            @endif
            @if ($bodies->contains('SUV'))
                <a href="{{ route('stock', ['body' => 'SUV']) }}">SUVs</a>
            @endif
        </div>
    </section>

    <section class="stock-trust" aria-label="What is included">
        <div>
            <span class="stock-trust-icon amber">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l7 3v6c0 4.2-2.8 7.4-7 9-4.2-1.6-7-4.8-7-9V6l7-3z" fill="none" stroke="currentColor" stroke-width="1.8"></path><path d="M8.5 12.2l2.2 2.2 4.8-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            </span>
            <div><b>AA Approved Dealer</b><small>Sold from this forecourt</small></div>
        </div>
        <div>
            <span class="stock-trust-icon teal">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l7 3v6c0 4.2-2.8 7.4-7 9-4.2-1.6-7-4.8-7-9V6l7-3z" fill="currentColor"></path></svg>
            </span>
            <div><b>3-month warranty</b><small>Included with the car</small></div>
        </div>
        <div>
            <span class="stock-trust-icon sky">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16h16M6 16l1.2-4.2A2 2 0 019.1 10h5.8a2 2 0 011.9 1.8L18 16M7 16v2M17 16v2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>
            </span>
            <div><b>12-month breakdown cover</b><small>Included with the car</small></div>
        </div>
        <div>
            <span class="stock-trust-icon slate">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.2 4.2L19 7.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            </span>
            <div><b>HPI checked</b><small>Checked before the car is advertised</small></div>
        </div>
    </section>

    <div class="stock-layout">
        <form class="stock-search" method="get" action="{{ route('stock') }}" data-live-search="#stock-results">
            <button class="stock-filter-toggle" type="button" data-filters-toggle aria-expanded="false" aria-controls="stock-filters">Show filters</button>
            <aside id="stock-filters" class="stock-filters" data-filters>
                <div class="stock-filters-head">
                    <h2>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>
                        Refine search
                    </h2>
                    <a href="{{ route('stock') }}">Reset all</a>
                </div>

                <label>Search vehicle
                    <span class="stock-searchbox">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="1.8"></circle><path d="M16 16l4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="Make, model or registration">
                    </span>
                </label>

                <label>Make
                    <select name="make" data-make>
                        <option value="">All makes ({{ number_format($stockTotal) }})</option>
                        @foreach ($makes as $make)
                            <option value="{{ $make }}" @selected(request('make') === $make)>{{ $make }} ({{ number_format((int) ($makeCounts[$make] ?? 0)) }})</option>
                        @endforeach
                    </select>
                </label>

                <label>Model
                    <select name="model" data-model>
                        <option value="">All models</option>
                        @foreach ($modelRows as $row)
                            <option value="{{ $row->model }}" data-make="{{ $row->make }}" @selected(request('model') === $row->model)>{{ $row->model }}</option>
                        @endforeach
                    </select>
                </label>

                @if ($priceCeiling > $priceFloor)
                    <div class="stock-budget">
                        <div>
                            <span>Price budget</span>
                            <b data-price-label>@if(request()->filled('price')) Up to £{{ number_format((int) request('price')) }} @else Any price @endif</b>
                        </div>
                        <input data-price-slider type="range" name="price" min="{{ $priceFloor }}" max="{{ $priceCeiling }}" step="500" value="{{ max($priceFloor, min($chosenPrice, $priceCeiling)) }}">
                        <div><span>£{{ number_format($priceFloor) }}</span><span>£{{ number_format($priceCeiling) }}</span></div>
                    </div>
                @endif

                <div class="stock-pair">
                    <label>Gearbox
                        <select name="transmission">
                            <option value="">Any</option>
                            @foreach ($transmissions as $option)
                                <option value="{{ $option }}" @selected(request('transmission') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Fuel
                        <select name="fuel">
                            <option value="">Any</option>
                            @foreach ($fuels as $option)
                                <option value="{{ $option }}" @selected(request('fuel') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="stock-pair">
                    <label>Min year
                        <select name="year">
                            <option value="">Any</option>
                            @foreach ($years as $year)
                                <option value="{{ $year }}" @selected((string) request('year') === (string) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Max miles
                        <select name="mileage">
                            <option value="">Any</option>
                            @foreach ($mileageOptions as $miles)
                                <option value="{{ $miles }}" @selected((string) request('mileage') === (string) $miles)>{{ number_format($miles) }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <label>Body type
                    <select name="body">
                        <option value="">All body styles</option>
                        @foreach ($bodies as $option)
                            <option value="{{ $option }}" @selected(request('body') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>

                @auth
                    <label class="stock-check">
                        <span>Saved cars only ({{ count($saved) }})</span>
                        <input type="checkbox" name="saved" value="1" @checked(request()->boolean('saved'))>
                    </label>
                @endauth

                <button class="stock-apply" type="submit" data-stock-apply>Show cars</button>

                <div class="stock-help">
                    <div>
                        <b>Need assistance?</b>
                        <span>Send the team a message about a car.</span>
                    </div>
                    <div>
                        @if ($phone)
                            <a href="tel:+{{ $phone }}">Call</a>
                            <a class="whatsapp" href="https://wa.me/{{ $phone }}" target="_blank" rel="noopener">WhatsApp</a>
                        @endif
                        <button type="button" data-enquiry-open>Enquire</button>
                    </div>
                </div>
            </aside>

            <div class="stock-controls">
                <div class="stock-views" role="group" aria-label="Layout">
                    <button type="button" data-stock-view="grid" class="is-on" aria-label="Grid view">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z" fill="currentColor"></path></svg>
                    </button>
                    <button type="button" data-stock-view="list" aria-label="List view">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>
                    </button>
                </div>
                <label>Sort by
                    <select name="sort">
                        @foreach (['featured' => 'Recommended', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'mileage' => 'Mileage: lowest', 'newest' => 'Year: newest'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('sort', 'featured') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </form>

        <div id="stock-results" class="stock-results" data-results>
            @include('partials.stock-results')
        </div>

        <section class="stock-visit">
            <div>
                <b>Visit the Dagenham forecourt</b>
                <p>{{ $address !== '' ? $address : 'Rainham Road South, Dagenham RM10 8YT' }}</p>
            </div>
            <div>
                @if ($phone)
                    <a href="tel:+{{ $phone }}">Call the dealership</a>
                @endif
                <button type="button" data-enquiry-open>Send an enquiry</button>
            </div>
        </section>
    </div>
</div>
@endsection
