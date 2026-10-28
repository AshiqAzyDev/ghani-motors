@extends('layouts.dealer')
@section('title', 'Leads')
@section('actions')
    @if (auth('staff')->user()->can('leads.view'))
        <a class="btn smallbtn" href="{{ route('dealer.export', 'leads') }}">Export CSV</a>
    @endif
@endsection
@section('content')
<form method="get" class="list-bar">
    <select name="status" onchange="this.form.submit()">
        <option value="">Any status</option>
        @foreach (['new','contacted','closed'] as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
        @endforeach
    </select>
</form>
<div class="panel table-scroll">
    <table class="data-table">
        <thead>
            <tr><th class="sno">S.no</th><th>Subject</th><th>Customer</th><th>Requirements</th><th>Type</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="sno">{{ $rows->firstItem() + $loop->index }}</td>
                    <td>
                        <a href="{{ route('dealer.leads.show', $row) }}"><b>{{ $row->subject }}</b></a>
                        @if($row->valuation)<small>{{ $row->valuation->registration }} · {{ $row->valuation->mileage }} miles</small>@endif
                        @if($row->contact_notes || $row->additional_notes)<small>Has notes</small>@endif
                    </td>
                    <td>{{ $row->customer?->name }}<small>{{ $row->customer?->phone }}</small></td>
                    <td>
                        @forelse ($row->requirementPairs() as $pair)
                            <span class="req-pill">{{ $pair['label'] }}: {{ $pair['value'] }}</span>
                        @empty
                            —
                        @endforelse
                    </td>
                    <td>{{ ucfirst(str_replace('_',' ', $row->type)) }}</td>
                    <td><span class="badge {{ $row->status }}">{{ ucfirst($row->status) }}</span></td>
                    <td class="row-actions"><a class="rowbtn" href="{{ route('dealer.leads.show', $row) }}">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-row">No leads match this filter.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $rows->links() }}
@endsection
