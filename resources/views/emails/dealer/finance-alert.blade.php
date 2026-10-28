@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">A finance enquiry has arrived from the website.</p>
@include('emails.partials.details', ['rows' => [
    'Customer' => $customer->name,
    'Email' => $customer->email,
    'Phone' => $customer->phone,
    'Car' => $vehicle?->title(),
    'Message' => $lead->message,
]])
@endsection
