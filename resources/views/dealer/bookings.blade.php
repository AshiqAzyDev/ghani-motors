@extends('layouts.dealer')
@section('title', 'Bookings')
@section('actions')
    @if (auth('staff')->user()->can('bookings.view'))
        <a class="btn smallbtn" href="{{ route('dealer.export', 'bookings') }}">Export CSV</a>
    @endif
@endsection
@section('content')
<form class="list-bar" method="get">
    <input type="hidden" name="view" value="{{ $view }}">
    <select name="type"><option value="">Any type</option><option value="reservation" @selected(request('type') === 'reservation')>Reservation</option><option value="viewing" @selected(request('type') === 'viewing')>Viewing</option><option value="test_drive" @selected(request('type') === 'test_drive')>Test drive</option></select>
    <select name="status"><option value="">Any status</option>@foreach (['held','requested','confirmed','completed','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
    <button class="btn smallbtn" type="submit">Filter</button>
    <a class="btn smallbtn ghost" href="{{ route('dealer.bookings', array_merge(request()->except('view'), ['view' => 'list'])) }}">List</a>
    <a class="btn smallbtn ghost" href="{{ route('dealer.bookings', array_merge(request()->except('view'), ['view' => 'calendar'])) }}">Calendar</a>
</form>
@if (auth('staff')->user()->can('bookings.manage'))
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

@if ($view === 'calendar')
    @php
        $start = $month->copy()->startOfMonth()->startOfWeek(Carbon\Carbon::MONDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(Carbon\Carbon::SUNDAY);
        $cursor = $start->copy();
    @endphp
    <div class="list-bar" style="justify-content:space-between;">
        <a class="btn smallbtn ghost" href="{{ route('dealer.bookings', ['view' => 'calendar', 'month' => $month->copy()->subMonth()->format('Y-m')]) }}">← {{ $month->copy()->subMonth()->format('M Y') }}</a>
        <strong>{{ $month->format('F Y') }}</strong>
        <a class="btn smallbtn ghost" href="{{ route('dealer.bookings', ['view' => 'calendar', 'month' => $month->copy()->addMonth()->format('Y-m')]) }}">{{ $month->copy()->addMonth()->format('M Y') }} →</a>
    </div>
    <div class="calendar-grid" style="margin-top:1rem;">
        @while ($cursor <= $end)
            @php($key = $cursor->format('Y-m-d'))
            <div class="calendar-cell @if(!$cursor->isSameMonth($month)) is-muted @endif">
                <strong>{{ $cursor->day }}</strong>
                @foreach (($calendarBookings[$key] ?? []) as $item)
                    <a class="calendar-item" href="{{ route('dealer.bookings.show', $item) }}">
                        {{ \Illuminate\Support\Str::of($item->preferred_time)->substr(0, 5) }}
                        {{ ucfirst(str_replace('_', ' ', $item->type)) }}
                        · {{ $item->customer?->name }}
                    </a>
                @endforeach
            </div>
            @php($cursor->addDay())
        @endwhile
    </div>
@else
    <div class="panel table-scroll">
        <table class="data-table">
            <thead>
                <tr><th class="sno">S.no</th><th>When</th><th>Type</th><th>Car</th><th>Customer</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="sno">{{ $rows->firstItem() + $loop->index }}</td>
                        <td>{{ $row->preferred_on?->format('j M Y') }} {{ $row->preferred_time }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $row->type)) }}</td>
                        <td>{{ $row->vehicle?->title() }}</td>
                        <td>@if($row->customer)<a href="{{ route('dealer.customers.show', $row->customer) }}">{{ $row->customer->name }}</a>@endif</td>
                        <td><span class="badge {{ $row->status }}">{{ ucfirst($row->status) }}</span></td>
                        <td class="row-actions">
                            <a class="rowbtn" href="{{ route('dealer.bookings.show', $row) }}">View</a>
                            @if (auth('staff')->user()->can('bookings.manage'))
                                @foreach ($row->nextStatuses() as $next)
                                    <form method="post" action="{{ route('dealer.bookings.status', $row) }}">@csrf<input type="hidden" name="status" value="{{ $next }}"><button class="rowbtn" type="submit">{{ ucfirst($next) }}</button></form>
                                @endforeach
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-row">No bookings match this filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $rows->links() }}
@endif
@endsection
