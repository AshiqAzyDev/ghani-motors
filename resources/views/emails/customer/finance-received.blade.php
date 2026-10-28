@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">Hi {{ $name }},</p>
<p style="margin:0 0 12px;">Thanks for your finance enquiry. This is an enquiry only. A member of the team will talk you through the options.</p>
@include('emails.partials.details', ['rows' => [
    'Car' => $vehicle?->title(),
    'Message' => $lead->message,
]])
@endsection
