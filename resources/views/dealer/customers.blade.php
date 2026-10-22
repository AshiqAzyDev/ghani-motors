@extends('layouts.dealer')
@section('title', 'Customers')
@section('content')
<h1>Customers</h1>
<form method="get" class="row"><input name="q" value="{{ request('q') }}" placeholder="Name, email, phone"><button class="btn" type="submit">Search</button></form>
@if (auth('staff')->user()->canMutate())
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
<div class="panel"><table>
    <tr><th>Name</th><th>Email</th><th>Phone</th><th>Source</th></tr>
    @foreach ($rows as $row)
        <tr><td>{{ $row->name }}</td><td>{{ $row->email }}</td><td>{{ $row->phone }}</td><td>{{ str_replace('_',' ', $row->source) }}</td></tr>
    @endforeach
</table></div>
{{ $rows->links() }}
@endsection
