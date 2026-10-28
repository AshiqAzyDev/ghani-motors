@extends('emails.layout')
@section('body')
<p style="margin:0 0 12px;">Hi {{ $name }},</p>
<p style="margin:0 0 12px;">Please find the details for {{ $invoice->kind === 'credit_note' ? 'credit note' : 'invoice' }} <strong>{{ $invoice->number }}</strong>.</p>
@include('emails.partials.details', ['rows' => [
    'Number' => $invoice->number,
    'Total' => $total,
    'Status' => ucfirst(str_replace('_', ' ', $invoice->status)),
    'Vehicle' => data_get($invoice->vehicle_snapshot, 'label'),
]])
<p style="margin:0;">If you have a question about this invoice, reply to this email or call the showroom.</p>
@endsection
