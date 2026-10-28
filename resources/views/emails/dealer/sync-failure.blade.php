@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">An Autotrader sync or webhook issue needs attention.</p>
@include('emails.partials.details', ['rows' => [
    'Detail' => $detail,
]])
<p style="margin:0;">Check the dealer overview for open API pauses and failed webhooks.</p>
@endsection
