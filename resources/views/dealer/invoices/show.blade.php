@extends('layouts.dealer')
@section('title', $invoice->number)
@section('actions')
    <a class="btn smallbtn ghost" href="{{ route('dealer.invoices') }}">Back to invoices</a>
    <a class="btn smallbtn" href="{{ route('dealer.invoices.print', $invoice) }}" target="_blank" rel="noopener">Print</a>
@endsection
@section('content')
<nav class="inv-toolbar no-print" aria-label="Invoice actions">
    <div class="inv-toolbar-left">
        <a href="{{ route('dealer.invoices') }}">← Back to invoices</a>
        <span class="inv-toolbar-sep" aria-hidden="true">|</span>
        <span class="inv-status-pill {{ $invoice->status }}">
            <i aria-hidden="true"></i>
            {{ ucfirst(str_replace('_', ' ', $invoice->status)) }}
        </span>
    </div>
    <div class="inv-toolbar-right">
        <a class="btn smallbtn" href="{{ route('dealer.invoices.print', $invoice) }}" target="_blank" rel="noopener">Print invoice</a>
        <a class="btn smallbtn ghost" href="{{ route('dealer.invoices.print', $invoice) }}" target="_blank" rel="noopener">PDF</a>
    </div>
</nav>

@include('dealer.invoices._document', ['invoice' => $invoice, 'dealer' => $dealer])

@if (auth('staff')->user()->can('invoices.manage'))
    <div class="inv-manage no-print">
        <div class="row">
            @if ($invoice->status === 'draft')
                <form method="post" action="{{ route('dealer.invoices.issue', $invoice) }}">@csrf<button class="btn" type="submit">Issue</button></form>
            @endif
            @if (in_array($invoice->status, ['draft', 'issued']) && $invoice->payments->isEmpty())
                <form method="post" action="{{ route('dealer.invoices.void', $invoice) }}">@csrf<button class="btn danger" type="submit">Void</button></form>
            @endif
            @if ($invoice->kind === 'invoice' && in_array($invoice->status, ['issued', 'part_paid', 'paid']))
                <form method="post" action="{{ route('dealer.invoices.credit', $invoice) }}">@csrf<button class="btn secondary" type="submit">Credit note</button></form>
            @endif
        </div>
        @if (in_array($invoice->status, ['issued', 'part_paid']) && $invoice->kind === 'invoice')
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
    </div>
@endif
@endsection
