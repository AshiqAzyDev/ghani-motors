@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">A sale has been marked complete in the dealer workspace.</p>
@include('emails.partials.details', ['rows' => [
    'Customer' => $customer->name,
    'Car' => $vehicle->title(),
    'Registration' => $sale->registration ?: $vehicle->registration,
    'Sale price' => $price,
    'Sold on' => optional($sale->sold_on)->format('d M Y') ?? $sale->sold_on,
]])
@endsection
