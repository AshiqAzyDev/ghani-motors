@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">Hi {{ $name }},</p>
<p style="margin:0 0 12px;">This is a reminder that your {{ $label }} is due tomorrow.</p>
@include('emails.partials.details', ['rows' => [
    'Car' => $vehicle->title(),
    'Date' => optional($booking->preferred_on)->format('d M Y') ?? $booking->preferred_on,
    'Time' => $booking->preferred_time,
]])
@endsection