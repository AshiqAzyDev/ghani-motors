@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">A reservation has been held on the website.</p>
@include('emails.partials.details', ['rows' => [
    'Customer' => $customer->name,
    'Email' => $customer->email,
    'Phone' => $customer->phone,
    'Car' => $vehicle->title(),
    'Registration' => $vehicle->registration,
    'Preferred date' => optional($booking->preferred_on)->format('d M Y') ?? $booking->preferred_on,
    'Notes' => $booking->notes,
]])
@endsection
