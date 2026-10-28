@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">Hi {{ $name }},</p>
<p style="margin:0 0 12px;">We have reserved this car for you while the team confirms the next steps.</p>
@include('emails.partials.details', ['rows' => [
    'Car' => $vehicle->title(),
    'Registration' => $vehicle->registration,
    'Preferred date' => optional($booking->preferred_on)->format('d M Y') ?? $booking->preferred_on,
    'Notes' => $booking->notes,
]])
@endsection
