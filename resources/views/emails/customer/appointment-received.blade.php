@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">Hi {{ $name }},</p>
<p style="margin:0 0 12px;">We have your {{ $label }} request. The team will confirm the time with you.</p>
@include('emails.partials.details', ['rows' => [
    'Car' => $vehicle->title(),
    'Date' => optional($booking->preferred_on)->format('d M Y') ?? $booking->preferred_on,
    'Time' => $booking->preferred_time,
    'Notes' => $booking->notes,
]])
@endsection
