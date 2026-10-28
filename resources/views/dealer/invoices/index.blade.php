@extends('layouts.dealer')
@section('title', 'Invoices')
@section('actions')
    @if (auth('staff')->user()->can('invoices.manage'))
        <a class="btn smallbtn" href="{{ route('dealer.invoices.create') }}">New invoice</a>
    @endif
@endsection
@section('content')
<form method="get" class="list-bar">
    <input name="q" value="{{ request('q') }}" placeholder="Number, customer, car">
    <input type="date" name="from" value="{{ request('from') }}" aria-label="From date">
    <input type="date" name="to" value="{{ request('to') }}" aria-label="To date">
    <select name="status"><option value="">Any status</option>@foreach (['draft','issued','part_paid','paid','void','credited'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_',' ', $status)) }}</option>@endforeach</select>
    <button class="btn smallbtn" type="submit">Filter</button>
</form>
<div class="panel table-scroll">
    <table class="data-table">
        <thead>
            <tr><th class="sno">S.no</th><th>Number</th><th>Customer</th><th>Scheme</th><th class="num">Total</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="sno">{{ $rows->firstItem() + $loop->index }}</td>
                    <td><a href="{{ route('dealer.invoices.show', $row) }}">{{ $row->number }}</a><small>{{ ucfirst(str_replace('_',' ', $row->kind)) }}</small></td>
                    <td>{{ $row->customer?->name }}</td>
                    <td>{{ ucfirst(str_replace('_',' ', $row->vat_scheme)) }}</td>
                    <td class="num">@gbp($row->total_pence)</td>
                    <td><span class="badge {{ $row->status }}">{{ ucfirst(str_replace('_',' ', $row->status)) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-row">No invoices match this filter.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $rows->links() }}
@endsection
