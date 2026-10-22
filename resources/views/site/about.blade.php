@extends('layouts.site')
@section('title', 'About')
@section('content')
<section class="section about">
    <div>
        <div class="crumb"><a href="{{ route('home') }}">Home</a> / About us</div>
        <div class="eyebrow">Good cars. Real people.</div>
        <h2>A family business.<br>An AA Approved Dealer.</h2>
        <p>Based in {{ $dealer->city }}, Ghani Motors is a family-run, AA Approved Dealer focused on quality used cars and a friendly, straightforward buying experience.</p>
        <p>{{ $dealer->address_line }}, {{ $dealer->city }} {{ $dealer->postcode }}</p>
        @if ($dealer->whatsappNumber())
            <p><a class="textlink" href="tel:+{{ $dealer->whatsappNumber() }}">{{ $dealer->phone }}</a></p>
        @endif
    </div>
    <div>
        <div class="benefit"><h3><span>01</span> Find the right fit</h3><p>Search by the things that matter to you, from price and mileage to transmission.</p></div>
        <div class="benefit"><h3><span>02</span> Make it yours</h3><p>Arrange a viewing, ask a question or explore finance with our team.</p></div>
        <div class="benefit"><h3><span>03</span> A simpler changeover</h3><p>Have a car to sell? Ask us about selling or part exchanging.</p></div>
    </div>
</section>
@include('partials.trust')
@include('partials.map')
@endsection
