@extends('layouts.site')
@section('title', 'Finance')
@section('description', 'An illustrative finance example for used cars at Ghani Motors. This is not a quote or a credit check.')
@section('content')
<section class="pageintro">
    <div class="crumb"><a href="{{ route('home') }}">Home</a> / Car finance</div>
    <div class="eyebrow">Explore your possibilities</div>
    <h1>A next step that fits your budget.</h1>
    <p class="sub">Explore an illustrative monthly payment, then talk to our team about finance options.</p>
</section>
<section class="section finance-grid">
    <div class="panel calc" data-calculator>
        <h2>Make the numbers work for you.</h2>
        <p>Adjust the amount, deposit and term to see an example.</p>
        @if ($car)
            <p><strong>{{ $car->title() }}</strong></p>
        @endif
        <label>Vehicle price <span class="calcvalue" data-price-label></span>
            <input type="range" name="price" min="1000" max="{{ max(40000, $car ? intdiv((int) $car->publicPricePence(), 100) : 40000) }}" step="50" value="{{ $car ? intdiv((int) $car->publicPricePence(), 100) : 15995 }}">
        </label>
        <label>Deposit <span class="calcvalue" data-deposit-label></span>
            <input type="range" name="deposit" min="0" max="{{ $car ? intdiv((int) $car->publicPricePence(), 100) : 15995 }}" step="50" value="2000">
        </label>
        <label>Agreement length
            <select name="months">
                @foreach ([24, 36, 48, 60] as $months)
                    <option value="{{ $months }}" @selected($months === 48)>{{ $months }} months</option>
                @endforeach
            </select>
        </label>
        <div class="payment">
            <small>ILLUSTRATIVE MONTHLY PAYMENT</small>
            <strong data-payment>—</strong>
            <small>Calculated at an assumed 12.9% annual rate</small>
        </div>
        <p class="sample">This is an illustration, not a finance offer or a credit check. Actual APR, fees and eligibility depend on the lender.</p>
    </div>
    <div>
        <div class="eyebrow">Let’s find a way forward</div>
        <h2>Finance starts<br>with a conversation.</h2>
        <p class="sub">Tell us which car you’re interested in and how to reach you.</p>
        <form class="panel" method="post" action="{{ route('finance.store') }}">
            @csrf
            @if ($car)<input type="hidden" name="car" value="{{ $car->slug }}">@endif
            @include('partials.contact-fields')
            <label>Message<textarea name="message">{{ old('message') }}</textarea></label>
            <button class="btn" type="submit" style="margin-top:1rem;">Send enquiry</button>
        </form>
    </div>
</section>
@endsection
