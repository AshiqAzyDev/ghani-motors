@extends('layouts.dealer')
@section('title', 'Invoices')
@section('content')
<div class="row" style="justify-content:space-between;"><h1>Invoices</h1>@if(auth('staff')->user()->canMutate())<a class="btn" href="{{ route('dealer.invoices.create') }}">New invoice</a>@endif</div>
<form method="get"><select name="status" onchange="this.form.submit()"><option value="">Any status</option>@foreach (['draft','issued','part_paid','paid','void','credited'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_',' ', $status) }}</option>@endforeach</select></form>
<div class="panel" style="margin-top:1rem;"><table>
    <tr><th>Number</th><th>Customer</th><th>Scheme</th><th>Total</th><th>Status</th></tr>
    @foreach ($rows as $row)
        <tr>
            <td><a href="{{ route('dealer.invoices.show', $row) }}">{{ $row->number }}</a><br><span class="meta">{{ str_replace('_',' ', $row->kind) }}</span></td>
            <td>{{ $row->customer?->name }}</td>
            <td>{{ str_replace('_',' ', $row->vat_scheme) }}</td>
            <td>@gbp($row->total_pence)</td>
            <td>{{ str_replace('_',' ', $row->status) }}</td>
        </tr>
    @endforeach
</table></div>
{{ $rows->links() }}
@endsection
