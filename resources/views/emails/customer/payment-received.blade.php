@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">Hi {{ $name }},</p>
<p style="margin:0 0 12px;">We have recorded a payment on invoice <strong>{{ $invoice->number }}</strong>.</p>
@include('emails.partials.details', ['rows' => [
    'Amount received' => $amount,
    'Method' => str_replace('_', ' ', $payment->method),
    'Reference' => $payment->reference,
    'Remaining balance' => $balance,
]])
@endsection
