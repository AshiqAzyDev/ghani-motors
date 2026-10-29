@extends('layouts.site')
@section('title', 'Used cars in Dagenham')
@section('content')
@php
    $place = trim(($dealer->address_line ?? 'Rainham Road South').', '.($dealer->city ?? 'Dagenham').', '.($dealer->postcode ?? 'RM10 8YT'));
    $phone = $dealer->whatsappNumber();
    $hasAutomatic = $transmissions->contains(fn ($value) => str_contains(strtolower((string) $value), 'automatic'));
    $automaticValue = $transmissions->first(fn ($value) => str_contains(strtolower((string) $value), 'automatic')) ?? 'Automatic';
    $hasDiesel = $fuels->contains(fn ($value) => strcasecmp((string) $value, 'Diesel') === 0);
    $hasSuv = $bodies->contains(fn ($value) => str_contains(strtolower((string) $value), 'suv'));
    $suvValue = $bodies->first(fn ($value) => str_contains(strtolower((string) $value), 'suv')) ?? 'SUV';
    $hasLowMileage = collect($mileages)->isNotEmpty();
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
            <div class="home-trust-bar" aria-label="Dealer trust">
                <span class="home-trust-aa">AA</span>
                <div>
                    <strong>Approved Dealer</strong>
                    <span>HPI Cleared · Quality cars. Added confidence.</span>
                </div>
            </div>
            <div class="eyebrow">Your next car. A more personal journey.</div>
            <h1>Find your next car. <em>Love every single mile.</em></h1>
            <p class="home-hero-lead">Quality used cars, a friendly team and a straightforward way to buy. Welcome to Ghani Motors.</p>
            <div class="home-live-chip">
                <span class="home-live-dot" aria-hidden="true"></span>
                {{ number_format($stockTotal) }}+ Inspected Vehicles in Stock · {{ $dealer->city ?? 'Dagenham' }} Forecourt
            </div>
            <div class="home-hero-actions">
                <a class="btn" href="{{ route('stock') }}">Explore our cars <span>↗</span></a>
                <a class="btn ghost" href="{{ route('sell') }}">Sell your car</a>
            </div>
        </div>

        <div class="home-search">
            <div class="home-search-tabs">
                <span>Find your car</span>
                <a href="{{ route('finance') }}">Explore finance <span>↗</span></a>
            </div>
            <form class="home-search-form" action="{{ route('stock') }}" method="get" data-home-search>
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
                <label class="home-body-field">Body style
                    <select name="body" aria-label="Body style">
                        <option value="">Any style</option>
                        @foreach ($bodies as $option)
                            <option value="{{ $option }}" @selected(request('body') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="home-chip-fields" hidden>
                    <input type="hidden" name="transmission" value="{{ request('transmission') }}" data-home-chip-field="transmission">
                    <input type="hidden" name="fuel" value="{{ request('fuel') }}" data-home-chip-field="fuel">
                    <input type="hidden" name="mileage" value="{{ request('mileage') }}" data-home-chip-field="mileage">
                </div>
                <button class="btn home-search-submit" type="submit">Search <span>→</span></button>
                <div class="home-search-chips" aria-label="Quick filters">
                    @if ($hasAutomatic)
                        <button class="home-chip" type="button" data-home-chip data-field="transmission" data-value="{{ $automaticValue }}">Automatic</button>
                    @endif
                    @if ($hasSuv)
                        <button class="home-chip" type="button" data-home-chip data-field="body" data-value="{{ $suvValue }}">SUV</button>
                    @endif
                    @if ($hasLowMileage)
                        <button class="home-chip" type="button" data-home-chip data-field="mileage" data-value="30000">Low Mileage</button>
                    @endif
                    @if ($hasDiesel)
                        <button class="home-chip" type="button" data-home-chip data-field="fuel" data-value="Diesel">Diesel</button>
                    @endif
                </div>
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

    <section class="home-section home-why">
        <div class="home-section-head">
            <div>
                <h2>Why Ghani Motors?</h2>
                <p class="sub">Trust and peace of mind on every car from the forecourt.</p>
            </div>
            <span class="home-why-badge">AA Approved</span>
        </div>
        <div class="home-why-grid">
            <article>
                <span class="home-why-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 3l7 3v6c0 4.2-2.8 7.4-7 9-4.2-1.6-7-4.8-7-9V6l7-3z" fill="none" stroke="currentColor" stroke-width="1.8"></path></svg>
                </span>
                <h3>3-Month</h3>
                <p>Comprehensive warranty extendable up to 36 months.</p>
            </article>
            <article>
                <span class="home-why-icon is-amber" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 3l7 3v6c0 4.2-2.8 7.4-7 9-4.2-1.6-7-4.8-7-9V6l7-3z" fill="currentColor"></path></svg>
                </span>
                <h3>12 Months</h3>
                <p>Complimentary roadside AA breakdown cover.</p>
            </article>
            <article>
                <span class="home-why-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M4 16h16M6 16l1.2-4.2A2 2 0 019.1 10h5.8a2 2 0 011.9 1.8L18 16M7 16v2M17 16v2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>
                </span>
                <h3>Drive Away</h3>
                <p>5-day drive-away insurance available instantly.</p>
            </article>
            <article>
                <span class="home-why-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h10M4 17h14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>
                </span>
                <h3>Tailored</h3>
                <p>Fast approval HP &amp; PCP finance packages.</p>
            </article>
        </div>
    </section>

    <section class="home-section home-featured">
        <div class="home-section-head">
            <div>
                <div class="eyebrow home-featured-live"><i aria-hidden="true"></i> Featured Forecourt Arrival</div>
                <h2>Meet your next car.</h2>
                <p class="sub">A short selection from the forecourt. Search above to see every car.</p>
            </div>
            <a class="textlink" href="{{ route('stock') }}">View all ↗</a>
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

    <section class="home-section home-contact-strip">
        <div class="home-contact-card">
            <div>
                <span class="eyebrow">Ready for a test drive?</span>
                <h2>Speak with our {{ $dealer->city ?? 'Dagenham' }} Forecourt</h2>
            </div>
            <div class="home-contact-actions">
                @if ($phone)
                    <a class="btn" href="tel:+{{ $phone }}">{{ $dealer->phone ?: 'Call us' }}</a>
                    <a class="btn home-whatsapp" href="https://wa.me/{{ $phone }}?text={{ rawurlencode('Hi Ghani Motors, I have a question about a car.') }}" target="_blank" rel="noopener">WhatsApp</a>
                @else
                    <button class="btn" type="button" data-enquiry-open>Enquire</button>
                @endif
            </div>
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
