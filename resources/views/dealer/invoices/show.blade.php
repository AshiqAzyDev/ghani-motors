@extends('layouts.dealer')
@section('title', $invoice->number)
@section('content')
<div class="row" style="justify-content:space-between;">
    <h1>{{ $invoice->number }}</h1>
    <a class="btn ghost" href="{{ route('dealer.invoices.print', $invoice) }}" target="_blank">Print</a>
</div>
<div class="panel">
    <p>{{ str_replace('_', ' ', $invoice->kind) }} · {{ str_replace('_', ' ', $invoice->vat_scheme) }} · {{ str_replace('_', ' ', $invoice->status) }}</p>
    <p>{{ $invoice->customer_snapshot['name'] ?? '' }} · {{ $invoice->customer_snapshot['email'] ?? '' }}</p>
    <p>{{ $invoice->vehicle_snapshot['label'] ?? '' }} {{ $invoice->vehicle_snapshot['registration'] ?? '' }}</p>
    <table>
        <tr><th>Description</th><th>Qty</th><th>Net</th><th>VAT</th><th>Total</th></tr>
        @foreach ($invoice->lines as $line)
            <tr>
                <td>{{ $line->description }} <span class="meta">{{ str_replace('_',' ', $line->line_type) }}</span></td>
                <td>{{ $line->quantity }}</td>
                <td>@gbp($line->unit_net_pence)</td>
                <td>@gbp($line->vat_pence)</td>
                <td>@gbp($line->line_total_pence)</td>
            </tr>
        @endforeach
    </table>
    <p>Net @gbp($invoice->net_pence) · VAT @gbp($invoice->vat_pence) · Total @gbp($invoice->total_pence)</p>
    <p>Balance @gbp($invoice->balancePence())</p>
    @if ($invoice->payments->isNotEmpty())
        <h2>Payments</h2>
        @foreach ($invoice->payments as $payment)
            <p>{{ $payment->received_on?->format('j M Y') }} · @gbp($payment->amount_pence) · {{ $payment->method }} · {{ $payment->reference }}</p>
        @endforeach
    @endif
</div>
@if (auth('staff')->user()->canMutate())
    <div class="row" style="margin-top:1rem;">
        @if ($invoice->status === 'draft')
            <form method="post" action="{{ route('dealer.invoices.issue', $invoice) }}">@csrf<button class="btn" type="submit">Issue</button></form>
        @endif
        @if (in_array($invoice->status, ['draft','issued']) && $invoice->payments->isEmpty())
            <form method="post" action="{{ route('dealer.invoices.void', $invoice) }}">@csrf<button class="btn danger" type="submit">Void</button></form>
        @endif
        @if ($invoice->kind === 'invoice' && in_array($invoice->status, ['issued','part_paid','paid']))
            <form method="post" action="{{ route('dealer.invoices.credit', $invoice) }}">@csrf<button class="btn secondary" type="submit">Credit note</button></form>
        @endif
    </div>
    @if (in_array($invoice->status, ['issued','part_paid']) && $invoice->kind === 'invoice')
        <form class="panel" method="post" action="{{ route('dealer.invoices.pay', $invoice) }}" style="margin-top:1rem;">
            @csrf
            <h2>Record a payment</h2>
            <div class="row">
                <input name="amount" type="number" step="0.01" placeholder="Amount £" required>
                <input type="date" name="received_on" required value="{{ now()->toDateString() }}">
                <select name="method">@foreach (['bank_transfer','card','finance','cash'] as $method)<option value="{{ $method }}">{{ str_replace('_',' ', $method) }}</option>@endforeach</select>
                <input name="reference" placeholder="Reference" required>
            </div>
            <button class="btn" type="submit" style="margin-top:.6rem;">Save payment</button>
        </form>
    @endif
@endif
@endsection
