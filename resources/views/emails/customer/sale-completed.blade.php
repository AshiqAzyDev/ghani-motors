@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">Hi {{ $name }},</p>
<p style="margin:0 0 12px;">Thank you for buying with Ghani Motors. Your sale has been recorded.</p>
@include('emails.partials.details', ['rows' => [
    'Car' => $vehicle->title(),
    'Registration' => $sale->registration ?: $vehicle->registration,
    'Sale price' => $price,
    'Sold on' => optional($sale->sold_on)->format('d M Y') ?? $sale->sold_on,
]])
@endsection
