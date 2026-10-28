@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">Hi {{ $name }},</p>
<p style="margin:0 0 12px;">This is a reminder that we are holding a car for you.</p>
@include('emails.partials.details', ['rows' => [
    'Car' => $vehicle->title(),
    'Preferred date' => optional($booking->preferred_on)->format('d M Y') ?? $booking->preferred_on,
]])
@endsection
