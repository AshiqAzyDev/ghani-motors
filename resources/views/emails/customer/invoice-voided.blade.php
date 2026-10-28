@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">Hi {{ $name }},</p>
<p style="margin:0 0 12px;">Invoice <strong>{{ $invoice->number }}</strong> has been voided and no longer needs payment.</p>
@endsection
