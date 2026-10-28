@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">A new enquiry has arrived from the website.</p>
@include('emails.partials.details', ['rows' => [
    'Customer' => $customer->name,
    'Email' => $customer->email,
    'Phone' => $customer->phone,
    'Car' => $vehicle?->title(),
    'Subject' => $lead->subject,
    'Message' => $lead->message,
]])
@endsection
