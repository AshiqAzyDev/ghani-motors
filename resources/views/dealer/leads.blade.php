@extends('layouts.dealer')
@section('title', 'Leads')
@section('content')
<h1>Leads</h1>
<form method="get"><select name="status" onchange="this.form.submit()"><option value="">Any status</option>@foreach (['new','contacted','closed'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></form>
<div class="panel" style="margin-top:1rem;"><table>
    <tr><th>Subject</th><th>Customer</th><th>Type</th><th>Status</th><th></th></tr>
    @foreach ($rows as $row)
        <tr>
            <td>{{ $row->subject }}@if($row->valuation)<br><span class="meta">{{ $row->valuation->registration }} · {{ $row->valuation->mileage }} miles</span>@endif
                @foreach ($row->valuation?->photos ?? [] as $photo)
                    <br><a href="{{ route('dealer.photos', $photo) }}">Photo {{ $loop->iteration }}</a>
                @endforeach
            </td>
            <td>{{ $row->customer?->name }}<br><span class="meta">{{ $row->customer?->phone }}</span></td>
            <td>{{ str_replace('_',' ', $row->type) }}</td>
            <td>{{ $row->status }}</td>
            <td>
                @if (auth('staff')->user()->canMutate())
                    <form method="post" action="{{ route('dealer.leads.update', $row) }}">@csrf
                        <select name="status">@foreach (['new','contacted','closed'] as $status)<option @selected($row->status === $status)>{{ $status }}</option>@endforeach</select>
                        <button class="btn ghost" type="submit">Update</button>
                    </form>
                @endif
            </td>
        </tr>
    @endforeach
</table></div>
{{ $rows->links() }}
@endsection
