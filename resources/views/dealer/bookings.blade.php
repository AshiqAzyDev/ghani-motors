@extends('layouts.dealer')
@section('title', 'Bookings')
@section('content')
<h1>Bookings</h1>
<form class="row" method="get">
    <select name="type"><option value="">Any type</option><option value="reservation" @selected(request('type') === 'reservation')>Reservation</option><option value="viewing" @selected(request('type') === 'viewing')>Viewing</option><option value="test_drive" @selected(request('type') === 'test_drive')>Test drive</option></select>
    <select name="status"><option value="">Any status</option>@foreach (['held','requested','confirmed','completed','cancelled'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select>
    <button class="btn" type="submit">Filter</button>
</form>
@if (auth('staff')->user()->canMutate())
<form class="panel" method="post" action="{{ route('dealer.bookings.store') }}" style="margin:1rem 0;">
    @csrf
    <div class="row">
        <select name="vehicle_id" required><option value="">Car</option>@foreach ($cars as $car)<option value="{{ $car->id }}">{{ $car->title() }} ({{ $car->website_status }})</option>@endforeach</select>
        <select name="customer_id" required><option value="">Customer</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select>
        <select name="type"><option value="reservation">Reservation</option><option value="viewing">Viewing</option><option value="test_drive">Test drive</option></select>
        <input type="date" name="preferred_on" required>
        <input type="time" name="preferred_time">
    </div>
    <label>Notes</label><textarea name="notes"></textarea>
    <button class="btn" type="submit">Add booking</button>
</form>
@endif
<div class="panel">
    <table>
        <tr><th>When</th><th>Type</th><th>Car</th><th>Customer</th><th>Status</th><th></th></tr>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row->preferred_on?->format('j M Y') }} {{ $row->preferred_time }}</td>
                <td>{{ str_replace('_', ' ', $row->type) }}</td>
                <td>{{ $row->vehicle?->title() }}</td>
                <td>{{ $row->customer?->name }}</td>
                <td>{{ $row->status }}</td>
                <td>
                    @if (auth('staff')->user()->canMutate())
                        @foreach ($row->type === 'reservation' ? ($row->status === 'held' ? ['cancelled','completed'] : []) : match($row->status) { 'requested' => ['confirmed','cancelled'], 'confirmed' => ['completed','cancelled'], default => [] } as $next)
                            <form method="post" action="{{ route('dealer.bookings.status', $row) }}" style="display:inline;">@csrf<input type="hidden" name="status" value="{{ $next }}"><button class="btn ghost" type="submit">{{ $next }}</button></form>
                        @endforeach
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
</div>
{{ $rows->links() }}
@endsection
