@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">A sell / valuation enquiry has arrived.</p>
@include('emails.partials.details', ['rows' => [
    'Customer' => $customer->name,
    'Email' => $customer->email,
    'Phone' => $customer->phone,
    'Subject' => $lead->subject,
    'Message' => $lead->message,
]])
@endsection
