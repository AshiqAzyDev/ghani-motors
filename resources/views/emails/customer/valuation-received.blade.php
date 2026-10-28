@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">Hi {{ $name }},</p>
<p style="margin:0 0 12px;">Thanks for sending your car details. We will review them and contact you about selling or part exchange.</p>
@include('emails.partials.details', ['rows' => [
    'Subject' => $lead->subject,
    'Message' => $lead->message,
]])
@endsection
