@extends('layouts.dealer')
@section('title', 'Sales')
@section('actions')
    @if (auth('staff')->user()->can('sales.view'))
        <a class="btn smallbtn" href="{{ route('dealer.export', 'sales') }}">Export CSV</a>
    @endif
@endsection
@section('content')
@if (auth('staff')->user()->can('sales.manage'))
<form class="panel" method="post" action="{{ route('dealer.sales.store') }}" style="margin:1rem 0;">
    @csrf
    <div class="row">
        <select name="vehicle_id" required><option value="">Car</option>@foreach ($cars as $car)<option value="{{ $car->id }}">{{ $car->title() }}</option>@endforeach</select>
        <select name="customer_id" required><option value="">Customer</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select>
        <input name="price" type="number" step="0.01" placeholder="Price £" required>
        <input type="date" name="sold_on" required value="{{ now()->toDateString() }}">
        <select name="method">@foreach (['bank_transfer','card','finance','cash'] as $method)<option value="{{ $method }}">{{ str_replace('_',' ', $method) }}</option>@endforeach</select>
    </div>
    <label>Part exchange</label>
    <select name="part_exchange_valuation_id"><option value="">None</option>@foreach ($valuations as $valuation)<option value="{{ $valuation->id }}">{{ $valuation->registration }} {{ $valuation->make }} {{ $valuation->model }}</option>@endforeach</select>
    <label>Notes</label><textarea name="notes"></textarea>
    <button class="btn" type="submit">Record sale</button>
</form>
@endif
<div class="panel table-scroll">
    <table class="data-table">
        <thead>
            <tr><th class="sno">S.no</th><th>Date</th><th>Car</th><th>Customer</th><th class="num">Price</th><th>Invoice</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="sno">{{ $rows->firstItem() + $loop->index }}</td>
                    <td>{{ $row->sold_on?->format('j M Y') }}</td>
                    <td>{{ $row->vehicle_label }} <span class="badge {{ $row->status }}">{{ ucfirst($row->status) }}</span></td>
                    <td>@if($row->customer)<a href="{{ route('dealer.customers.show', $row->customer) }}">{{ $row->customer->name }}</a>@endif</td>
                    <td class="num">@gbp($row->price_pence)</td>
                    <td>@if($row->invoice)<a href="{{ route('dealer.invoices.show', $row->invoice) }}">{{ $row->invoice->number }}</a>@else — @endif</td>
                    <td class="row-actions">
                        <a class="rowbtn" href="{{ route('dealer.sales.show', $row) }}">View</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-row">No sales recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $rows->links() }}
@endsection
