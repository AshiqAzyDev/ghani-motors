@extends('layouts.dealer')
@section('title', $car->exists ? 'Edit vehicle' : 'Add manual car')
@section('content')
<h1>{{ $car->exists ? $car->title() : 'Add a manual car' }}</h1>
@if ($car->exists && $car->source === 'manual')
    <p>Manual cars stay off the public website. Reserved and sold come from bookings and sales.</p>
@endif
<form class="panel" method="post" action="{{ $car->exists ? route('dealer.vehicles.update', $car) : route('dealer.vehicles.store') }}">
    @csrf
    @if ($car->exists) @method('PUT') @endif
    @if (!$car->exists || $car->source === 'manual')
        <div class="row">
            <div><label>Registration</label><input name="registration" value="{{ old('registration', $car->registration) }}"></div>
            <div><label>Make</label><input name="make" required value="{{ old('make', $car->make) }}"></div>
            <div><label>Model</label><input name="model" required value="{{ old('model', $car->model) }}"></div>
        </div>
        <div class="row">
            <div><label>Trim</label><input name="trim" value="{{ old('trim', $car->trim) }}"></div>
            <div><label>Year</label><input name="year" value="{{ old('year', $car->year) }}"></div>
            <div><label>Mileage</label><input name="mileage" value="{{ old('mileage', $car->mileage) }}"></div>
        </div>
        <div class="row">
            <div><label>Fuel</label><input name="fuel" value="{{ old('fuel', $car->fuel) }}"></div>
            <div><label>Transmission</label><input name="transmission" value="{{ old('transmission', $car->transmission) }}"></div>
            <div><label>Body</label><input name="body" value="{{ old('body', $car->body) }}"></div>
            <div><label>Colour</label><input name="colour" value="{{ old('colour', $car->colour) }}"></div>
        </div>
        <label>Price (£)</label>
        <input name="price" value="{{ old('price', $car->publicPricePence() !== null ? number_format($car->publicPricePence()/100, 2, '.', '') : '') }}">
        <label>Description</label>
        <textarea name="description">{{ old('description', $car->description) }}</textarea>
        @if (!$car->exists || !in_array($car->website_status, ['reserved', 'sold'], true))
            <label>Internal status</label>
            <select name="website_status">
                <option value="available" @selected(old('website_status', $car->website_status) === 'available')>Available</option>
                <option value="preparation" @selected(old('website_status', $car->website_status) === 'preparation')>Preparation</option>
            </select>
        @endif
    @else
        <p>{{ $car->registration }} · {{ $car->advertiser_advert_status }} · {{ $car->at_lifecycle }} · stock {{ $car->at_stock_id }}</p>
        <p>Autotrader fields are updated by sync. You can keep internal costs and a workshop hold.</p>
        <label><input type="checkbox" name="workshop" value="1" style="width:auto;" @checked(old('workshop', $car->local_hold === 'preparation'))> Workshop hold (hides the car while it is prepared)</label>
    @endif
    <div class="row">
        <div><label>Purchase cost (£)</label><input name="purchase_cost" value="{{ old('purchase_cost', number_format(((int) $car->purchase_cost_pence) / 100, 2, '.', '')) }}"></div>
        <div><label>Prep cost (£)</label><input name="prep_cost" value="{{ old('prep_cost', number_format(((int) $car->prep_cost_pence) / 100, 2, '.', '')) }}"></div>
    </div>
    <label>Internal notes</label>
    <textarea name="internal_notes">{{ old('internal_notes', $car->internal_notes) }}</textarea>
    @if (auth('staff')->user()->canMutate())
        <button class="btn" type="submit" style="margin-top:1rem;">Save</button>
    @else
        <p>Your role can view this car but cannot change it.</p>
    @endif
</form>
@endsection
