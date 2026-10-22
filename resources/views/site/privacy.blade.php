@extends('layouts.site')
@section('title', 'Privacy')
@section('content')
<section class="section">
    <div class="wrap panel">
        <h1 style="font-size:3rem;">Privacy</h1>
        <p>{{ $dealer->name }} uses the details you submit to reply to enquiries, reservations, test drives, finance questions, and sell-your-car requests.</p>
        <p>We store your name, email, phone, and the car details you give us. Valuation photos are kept privately and are not published. Creating an account stores a password hash so you can see saved cars and your requests.</p>
        <p>Finance figures on this site are illustrations only. We do not send them to a lender from this form.</p>
        <p>To ask about your details, call {{ $dealer->phone }} or visit {{ $dealer->address_line }}, {{ $dealer->city }} {{ $dealer->postcode }}.</p>
    </div>
</section>
@endsection
