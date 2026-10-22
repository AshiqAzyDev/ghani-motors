@extends('layouts.site')
@section('title', 'Stock')
@section('description', 'Search used cars at Ghani Motors in Dagenham.')
@section('content')
<section class="pageintro">
    <div class="crumb"><a href="{{ route('home') }}">Home</a> / Used cars</div>
    <div class="eyebrow">The search starts here</div>
    <h1>Find a car that fits your life.</h1>
    <p class="sub">Your budget. Your priorities. Your next set of keys.</p>
</section>
<form class="inventory" method="get" action="{{ route('stock') }}" data-live-search="#stock-results">
    <button class="btn outline mobilefilter" type="button" data-filters-toggle>Show / hide filters</button>
    <div class="filters">
        <h3>Refine your search</h3>
        <label>Search<input type="search" name="q" value="{{ request('q') }}" placeholder="Make, model or registration"></label>
        <label>Make
            <select name="make" data-make>
                <option value="">Any make</option>
                @foreach ($makes as $make)
                    <option value="{{ $make }}" @selected(request('make') === $make)>{{ $make }}</option>
                @endforeach
            </select>
        </label>
        <label>Model
            <select name="model" data-model>
                <option value="">Any model</option>
                @foreach ($modelRows as $row)
                    <option value="{{ $row->model }}" data-make="{{ $row->make }}" @selected(request('model') === $row->model)>{{ $row->model }}</option>
                @endforeach
            </select>
        </label>
        <label>Maximum price
            <select name="price">
                <option value="">Any price</option>
                @foreach ([15000, 18000, 20000, 25000] as $band)
                    <option value="{{ $band }}" @selected((string) request('price') === (string) $band)>£{{ number_format($band) }}</option>
                @endforeach
            </select>
        </label>
        <label>Year from
            <select name="year">
                <option value="">Any year</option>
                @foreach (range((int) date('Y'), 2014) as $year)
                    <option value="{{ $year }}" @selected((string) request('year') === (string) $year)>{{ $year }}</option>
                @endforeach
            </select>
        </label>
        <label>Maximum mileage
            <select name="mileage">
                <option value="">Any mileage</option>
                @foreach ([30000, 50000, 70000, 100000] as $miles)
                    <option value="{{ $miles }}" @selected((string) request('mileage') === (string) $miles)>{{ number_format($miles) }} miles</option>
                @endforeach
            </select>
        </label>
        @foreach (['fuel' => $fuels, 'transmission' => $transmissions, 'body' => $bodies] as $name => $options)
            <label>{{ ucfirst($name) }}
                <select name="{{ $name }}">
                    <option value="">Any</option>
                    @foreach ($options as $option)
                        <option value="{{ $option }}" @selected(request($name) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
        @endforeach
        <label class="check"><input type="checkbox" name="saved" value="1" @checked(request()->boolean('saved'))> Saved cars only</label>
        <label>Sort
            <select name="sort">
                @foreach (['featured' => 'Recommended', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'newest' => 'Newest first', 'mileage' => 'Lowest mileage'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('sort', 'featured') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <a class="btn outline" href="{{ route('stock') }}">Reset filters</a>
    </div>
    <div class="results">
        <div id="stock-results" data-results>
            @include('partials.stock-results')
        </div>
    </div>
</form>
@endsection
