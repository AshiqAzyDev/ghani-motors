@extends('layouts.site')
@section('title', 'Used cars in Dagenham')
@section('content')
<section class="hero">
    <img src="{{ asset('images/hero.jpg') }}" alt="Car on an open road">
    <div class="herocopy">
        <div class="heroapproval">
            @include('partials.aa-badge')
            <span>Quality cars. Added confidence.</span>
        </div>
        <div class="eyebrow">Your next car. A more personal journey.</div>
        <h1>Find your next<br>car. <em>Love every<br>mile.</em></h1>
        <p>Quality used cars, a friendly team and a straightforward way to buy. Welcome to Ghani Motors.</p>
        <div class="buttons">
            <a class="btn" href="{{ route('stock') }}">Explore our cars <span>↗</span></a>
            <a class="btn ghost" href="{{ route('sell') }}">Sell your car</a>
        </div>
    </div>
</section>
<div class="search-wrap">
    <div class="search-tabs">
        <a href="{{ route('stock') }}">Find your next car</a>
        <a href="{{ route('finance') }}">Explore finance ↗</a>
    </div>
    <form class="search-row live" action="{{ route('stock') }}" method="get" data-live-search="#stock-results">
        <label>Search<input type="search" name="q" value="{{ request('q') }}" placeholder="Make, model or registration" aria-label="Search"></label>
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
                @foreach ([15000, 18000, 20000, 25000] as $band)
                    <option value="{{ $band }}" @selected((string) request('price') === (string) $band)>Up to £{{ number_format($band) }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn" type="submit">Search <span>→</span></button>
    </form>
</div>
@include('partials.trust')
<section class="section">
    <div class="sectionhead">
        <div>
            <div class="eyebrow">Ready for a new chapter?</div>
            <h2>Meet your next car.</h2>
            <p class="sub">Search the cars we have ready to view in Dagenham.</p>
        </div>
        <a class="textlink" href="{{ route('stock') }}">View all cars ↗</a>
    </div>
    <div id="stock-results" data-results>
        @include('partials.stock-results')
    </div>
</section>
<section class="section tight">
    <div class="split">
        <div class="promo">
            <span class="promo-icon">↔</span>
            <div class="eyebrow">Time for a change?</div>
            <h2>Your car could be<br>someone’s next chapter.</h2>
            <p>Selling or part exchanging? Tell us about your car and let’s take it from there.</p>
            <a class="btn" href="{{ route('sell') }}">Value my car ↗</a>
        </div>
        <div class="promo dark">
            <span class="promo-icon">£</span>
            <div class="eyebrow" style="color:#75d6e2">A way forward that fits you</div>
            <h2>Your next car.<br>Your kind of budget.</h2>
            <p>Explore finance options and talk to our team about a plan that suits your circumstances.</p>
            <a class="btn white" href="{{ route('finance') }}">Explore car finance ↗</a>
        </div>
    </div>
</section>
<section class="section about" id="about">
    <div>
        <div class="eyebrow">Good cars. Real people.</div>
        <h2>A family business.<br>An AA Approved Dealer.</h2>
        <p>Based in Dagenham, Ghani Motors is a family-run, AA Approved Dealer focused on quality used cars and a friendly, straightforward buying experience.</p>
        <a class="textlink" href="{{ route('about') }}">Visit us ↗</a>
    </div>
    <div>
        <div class="benefit"><h3><span>01</span> Find the right fit</h3><p>Search by the things that matter to you, from price and mileage to transmission.</p></div>
        <div class="benefit"><h3><span>02</span> Make it yours</h3><p>Arrange a viewing, ask a question or explore finance with our team.</p></div>
        <div class="benefit"><h3><span>03</span> A simpler changeover</h3><p>Have a car to sell? Ask us about selling or part exchanging.</p></div>
    </div>
</section>
@include('partials.map')
@endsection
