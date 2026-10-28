@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">A {{ $label }} request has been submitted.</p>
@include('emails.partials.details', ['rows' => [
    'Customer' => $customer->name,
    'Email' => $customer->email,
    'Phone' => $customer->phone,
    'Car' => $vehicle->title(),
    'Date' => optional($booking->preferred_on)->format('d M Y') ?? $booking->preferred_on,
    'Time' => $booking->preferred_time,
    'Notes' => $booking->notes,
]])
@endsection
