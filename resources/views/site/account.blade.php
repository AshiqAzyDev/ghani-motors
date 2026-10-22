@extends('layouts.site')
@section('title', 'Your account')
@section('content')
<section class="section">
    <div class="wrap">
        <h1 style="font-size:3rem;">Hello, {{ $customer->name }}</h1>
        <h2>Saved cars</h2>
        <div class="grid">
            @forelse ($saved as $car)
                @include('partials.car-card', ['car' => $car])
            @empty
                <p>You have not saved a car yet.</p>
            @endforelse
        </div>
        <h2>Bookings</h2>
        <div class="panel">
            @forelse ($bookings as $booking)
                <p>{{ ucfirst(str_replace('_', ' ', $booking->type)) }} · {{ $booking->vehicle?->title() }} · {{ $booking->preferred_on?->format('j M Y') }} {{ $booking->preferred_time }} · {{ $booking->status }}</p>
            @empty
                <p>No bookings yet.</p>
            @endforelse
        </div>
        <h2>Enquiries</h2>
        <div class="panel">
            @forelse ($leads as $lead)
                <p>{{ $lead->subject }} · {{ $lead->status }} · {{ $lead->created_on?->format('j M Y') }}</p>
            @empty
                <p>No enquiries yet.</p>
            @endforelse
        </div>
    </div>
</section>
@endsection
