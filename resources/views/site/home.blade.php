@extends('layouts.site')
@section('title', 'Used cars in Dagenham')
@section('content')
@php
    $place = trim(($dealer->address_line ?? 'Rainham Road South').', '.($dealer->city ?? 'Dagenham').', '.($dealer->postcode ?? 'RM10 8YT'));
@endphp
<div class="home-page">
    <section class="home-hero">
        <img src="{{ asset('images/hero.jpg') }}" alt="Car on an open road">
        <div class="home-hero-shade"></div>
        <div class="home-hero-copy">
            <div class="home-approval">
                @include('partials.aa-badge')
                <span>Quality cars. Added confidence.</span>
            </div>
            <div class="eyebrow">Your next car. A more personal journey.</div>
            <h1>Find your next car. <em>Love every mile.</em></h1>
            <p>Quality used cars, a friendly team and a straightforward way to buy. Welcome to Ghani Motors.</p>
            <div class="home-hero-actions">
                <a class="btn" href="{{ route('stock') }}">Explore our cars <span>↗</span></a>
                <a class="btn ghost" href="{{ route('sell') }}">Sell your car</a>
            </div>
        </div>

        <div class="home-search">
            <div class="home-search-tabs">
                <span>Find your next car</span>
                <a href="{{ route('finance') }}">Explore finance <span>↗</span></a>
            </div>
            <form class="home-search-form" action="{{ route('stock') }}" method="get">
                <label>Search
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Make, model or registration" aria-label="Search">
                </label>
                <label>Make
                    <select name="make" data-make aria-label="Make">
                        <option value="">Any make</option>
                        @foreach ($makes as $make)
                            <option value="{{ $make }}" @selected(request('make') === $make)>{{ $make }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Model
                    <select name="model" data-model aria-label="Model">
                        <option value="">Any model</option>
                        @foreach ($modelRows as $row)
                            <option value="{{ $row->model }}" data-make="{{ $row->make }}" @selected(request('model') === $row->model)>{{ $row->model }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Maximum price
                    <select name="price" aria-label="Maximum price">
                        <option value="">Any price</option>
                        @foreach ($prices as $band)
                            <option value="{{ $band }}" @selected((string) request('price') === (string) $band)>Up to £{{ number_format($band) }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="btn" type="submit">Search <span>→</span></button>
            </form>
            <div class="home-trust" aria-label="What is included">
                <div><b class="aa-small">AA</b> Approved Dealer</div>
                <div>HPI checked</div>
                <div>3-month warranty</div>
                <div>12-month breakdown cover</div>
                <div>5-day drive-away insurance</div>
                <div>Finance available</div>
            </div>
        </div>
    </section>

    <section class="home-section">
        <div class="home-section-head">
            <div>
                <div class="eyebrow">Ready for a new chapter?</div>
                <h2>Meet your next car.</h2>
                <p class="sub">A short selection from the forecourt. Search above to see every car.</p>
            </div>
            <a class="textlink" href="{{ route('stock') }}">View all cars ↗</a>
        </div>
        <div class="home-cards">
            @forelse ($cars as $car)
                @include('partials.car-card', ['car' => $car])
            @empty
                <div class="home-empty">
                    <h3>Featured cars will appear here.</h3>
                    <p>The full stock stays on the stock page.</p>
                    <a class="btn" href="{{ route('stock') }}">Browse all cars</a>
                </div>
            @endforelse
        </div>
    </section>

    <section class="home-section home-offers">
        <div class="home-offer">
            <span class="home-offer-mark" aria-hidden="true">↔</span>
            <div class="eyebrow">Time for a change?</div>
            <h2>Your car could be someone’s next chapter.</h2>
            <p>Selling or part exchanging? Tell us about your car and let’s take it from there.</p>
            <a class="btn" href="{{ route('sell') }}">Value my car ↗</a>
        </div>
        <div class="home-offer is-dark">
            <span class="home-offer-mark" aria-hidden="true">£</span>
            <div class="eyebrow">A way forward that fits you</div>
            <h2>Your next car. Your kind of budget.</h2>
            <p>Explore finance options and talk to our team about a plan that suits your circumstances.</p>
            <a class="btn white" href="{{ route('finance') }}">Explore car finance ↗</a>
        </div>
    </section>

    <section class="home-section home-story" id="about">
        <div class="home-story-copy">
            <div class="eyebrow">Good cars. Real people.</div>
            <h2>A family business. An AA Approved Dealer.</h2>
            <p>Based in Dagenham, Ghani Motors is a family-run, AA Approved Dealer focused on quality used cars and a friendly, straightforward buying experience.</p>
            <a class="textlink" href="{{ route('about') }}">Visit us ↗</a>
        </div>
        <div class="home-steps">
            <div>
                <span>01</span>
                <div>
                    <h3>Find the right fit</h3>
                    <p>Search by the things that matter to you, from price and mileage to transmission.</p>
                </div>
            </div>
            <div>
                <span>02</span>
                <div>
                    <h3>Make it yours</h3>
                    <p>Arrange a viewing, ask a question or explore finance with our team.</p>
                </div>
            </div>
            <div>
                <span>03</span>
                <div>
                    <h3>A simpler changeover</h3>
                    <p>Have a car to sell? Ask us about selling or part exchanging.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="home-section home-visit">
        <div class="home-map">
            <div class="home-map-frame">
                <iframe title="Ghani Motors on Google Maps" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="{{ $dealer->mapsEmbedUrl() }}"></iframe>
            </div>
            <div class="home-map-card">
                <b>{{ $dealer->address_line ?? 'Rainham Road South' }}</b>
                <span>{{ $dealer->city ?? 'Dagenham' }}, {{ $dealer->postcode ?? 'RM10 8YT' }}</span>
                <a href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($place) }}" target="_blank" rel="noopener">Open in Maps</a>
            </div>
        </div>
        <div class="home-visit-bar">
            <div>
                <b>{{ $place }}</b>
                <p>Come and see the cars on the forecourt.@if($dealer->opening_hours) {{ $dealer->opening_hours }}.@endif @if($dealer->publicEmail()) <a href="mailto:{{ $dealer->publicEmail() }}">{{ $dealer->publicEmail() }}</a>@endif</p>
            </div>
            <a class="btn" href="https://www.google.com/maps/dir/?api=1&destination={{ rawurlencode($place) }}" target="_blank" rel="noopener">Get directions</a>
        </div>
    </section>
</div>
@endsection
