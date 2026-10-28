@extends('layouts.dealer')
@section('title', 'Vehicles')
@section('actions')
    @if (auth('staff')->user()->can('vehicles.view'))
        <a class="btn smallbtn ghost" href="{{ route('dealer.export', 'vehicles') }}">Export CSV</a>
    @endif
    @if (auth('staff')->user()->can('vehicles.edit_manual'))
        <a class="btn smallbtn" href="{{ route('dealer.vehicles.create') }}">Add manual car</a>
    @endif
@endsection
@section('content')
<nav class="source-tabs" aria-label="Vehicle source">
    @foreach (['autotrader' => 'Autotrader', 'manual' => 'Manual'] as $key => $label)
        <a href="{{ route('dealer.vehicles', array_merge(request()->except(['page', 'source']), ['source' => $key])) }}" @class(['selected' => $source === $key])>{{ $label }} <span>{{ $sourceCounts[$key] ?? 0 }}</span></a>
    @endforeach
</nav>
<form class="list-bar" method="get">
    <input type="hidden" name="source" value="{{ $source }}">
    <input name="q" placeholder="Registration, make or model" value="{{ request('q') }}">
    <select name="status">
        <option value="">Any status</option>
        @foreach (['available','reserved','preparation','sold','hidden'] as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
        @endforeach
    </select>
    <button class="btn smallbtn" type="submit">Filter</button>
</form>
<div class="panel table-scroll">
    <table class="data-table vehicles-table">
        <thead>
            <tr>
                <th class="sno">S.no</th>
                <th>Registration</th>
                <th>Vehicle</th>
                <th>Year</th>
                <th>Mileage</th>
                <th>Fuel</th>
                <th>Status</th>
                <th class="num">Price</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($cars as $car)
                <tr>
                    <td class="sno">{{ $cars->firstItem() + $loop->index }}</td>
                    <td class="vrm">{{ $car->registration ?: '—' }}</td>
                    <td>
                        <b>{{ trim($car->make.' '.$car->model) ?: '—' }}</b>
                        @if ($car->trim)<small>{{ $car->trim }}</small>@endif
                    </td>
                    <td>{{ $car->year ?: '—' }}</td>
                    <td>{{ $car->mileage ? number_format($car->mileage) : '—' }}</td>
                    <td>{{ $car->fuel ?: '—' }}</td>
                    <td>
                        <span class="badge {{ $car->website_status }}">{{ ucfirst($car->website_status) }}</span>
                        @if ($car->featured)<small>Featured</small>@endif
                    </td>
                    <td class="num">@gbp($car->publicPricePence())</td>
                    <td class="row-actions">
                        @if (auth('staff')->user()->can('vehicles.edit_manual'))
                            <a class="rowbtn" href="{{ route('dealer.vehicles.edit', $car) }}">{{ $car->source === 'manual' ? 'Edit' : 'Internal' }}</a>
                        @endif
                        @if (auth('staff')->user()->can('vehicles.feature') && ($car->show_on_website || $car->featured))
                            <form method="post" action="{{ route('dealer.vehicles.featured', $car) }}">
                                @csrf
                                <input type="hidden" name="featured" value="{{ $car->featured ? 0 : 1 }}">
                                <button class="rowbtn" type="submit">{{ $car->featured ? 'Unfeature' : 'Feature' }}</button>
                            </form>
                        @endif
                        @if (auth('staff')->user()->can('vehicles.edit_manual') && $car->source === 'manual' && ! $car->sales_exists)
                            <form method="post" action="{{ route('dealer.vehicles.destroy', $car) }}">
                                @csrf
                                @method('DELETE')
                                <button class="rowbtn danger" type="submit">Remove</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="empty-row">No {{ $source === 'manual' ? 'manual' : 'Autotrader' }} cars match this filter.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $cars->links() }}
@endsection
