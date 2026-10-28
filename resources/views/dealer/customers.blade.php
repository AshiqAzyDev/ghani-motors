@extends('layouts.dealer')
@section('title', 'Customers')
@section('actions')
    @if (auth('staff')->user()->can('customers.view'))
        <a class="btn smallbtn" href="{{ route('dealer.export', 'customers') }}">Export CSV</a>
    @endif
@endsection
@section('content')
<form method="get" class="list-bar"><input name="q" value="{{ request('q') }}" placeholder="Name, email, phone"><button class="btn smallbtn" type="submit">Search</button></form>
@if (auth('staff')->user()->can('customers.manage'))
<form class="panel" method="post" action="{{ route('dealer.customers.store') }}" style="margin:1rem 0;">
    @csrf
    <div class="row">
        <input name="name" placeholder="Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input name="phone" placeholder="Phone" required>
        <input name="postcode" placeholder="Postcode">
        <select name="source">@foreach (['showroom','phone','website_enquiry','finance_enquiry','sell_your_car','other'] as $source)<option value="{{ $source }}">{{ str_replace('_',' ', $source) }}</option>@endforeach</select>
    </div>
    <label>Notes</label><textarea name="notes"></textarea>
    <button class="btn" type="submit">Add customer</button>
</form>
@endif
<div class="panel table-scroll">
    <table class="data-table">
        <thead>
            <tr><th class="sno">S.no</th><th>Name</th><th>Email</th><th>Phone</th><th>Source</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="sno">{{ $rows->firstItem() + $loop->index }}</td>
                    <td><a href="{{ route('dealer.customers.show', $row) }}">{{ $row->name }}</a></td>
                    <td>{{ $row->email }}</td>
                    <td>{{ $row->phone }}</td>
                    <td>{{ ucfirst(str_replace('_',' ', $row->source)) }}</td>
                    <td class="row-actions"><a class="rowbtn" href="{{ route('dealer.customers.show', $row) }}">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-row">No customers match this search.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $rows->links() }}
@endsection
