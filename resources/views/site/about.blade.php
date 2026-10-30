@extends('layouts.site')
@section('title', 'About')
@section('content')
<section class="section about">
    <div>
        <div class="crumb"><a href="{{ route('home') }}">Home</a> / About us</div>
        <div class="eyebrow">Good cars. Real people.</div>
        <h2>A family business.<br>An AA Approved Dealer.</h2>
        <p>Based in {{ $dealer->city ?: 'Dagenham' }}, {{ $dealer->displayName() }} is a family-run, AA Approved Dealer focused on quality used cars and a friendly, straightforward buying experience.</p>
        <p>{{ $dealer->showroomAddress() }}</p>
        @if ($dealer->opening_hours)
            <p>{{ $dealer->opening_hours }}</p>
        @endif
        @if ($dealer->phone)
            <p><a class="textlink" href="tel:{{ preg_replace('/\s+/', '', $dealer->phone) }}">{{ $dealer->phone }}</a></p>
        @endif
        @if ($dealer->publicEmail())
            <p><a class="textlink" href="mailto:{{ $dealer->publicEmail() }}">{{ $dealer->publicEmail() }}</a></p>
        @endif
    </div>
    <div>
        <div class="benefit"><h3><span>01</span> Find the right fit</h3><p>Search by the things that matter to you, from price and mileage to transmission.</p></div>
        <div class="benefit"><h3><span>02</span> Make it yours</h3><p>Arrange a viewing, ask a question or explore finance with our team.</p></div>
        <div class="benefit"><h3><span>03</span> A simpler changeover</h3><p>Have a car to sell? Ask us about selling or part exchanging.</p></div>
    </div>
</section>

@include('partials.google-reviews', [
    'dealer' => $dealer,
    'mapsUrl' => $dealer->mapsSearchUrl(),
])

@if (($policies ?? collect())->isNotEmpty())
    <section class="section about-policies" aria-labelledby="compliance-policies-heading">
        <div class="eyebrow">Transparency</div>
        <h2 id="compliance-policies-heading">Compliance Policies</h2>
        <p>Download our dealer policies for your records.</p>
        <div class="about-policies-list">
            @foreach ($policies as $policy)
                <a class="btn about-policy-btn" href="{{ route('compliance-policies.download', $policy) }}">{{ $policy->name }}</a>
            @endforeach
        </div>
    </section>
@endif

@include('partials.trust')
@include('partials.map')
@endsection
