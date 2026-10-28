@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">Hi {{ $name }},</p>
<p style="margin:0 0 12px;">Your booking status is now <strong>{{ $booking->status }}</strong>.</p>
@include('emails.partials.details', ['rows' => [
    'Type' => str_replace('_', ' ', $booking->type),
    'Car' => $vehicle->title(),
    'Date' => optional($booking->preferred_on)->format('d M Y') ?? $booking->preferred_on,
    'Time' => $booking->preferred_time,
]])
@endsection
