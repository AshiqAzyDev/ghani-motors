@extends('layouts.dealer')
@section('title', 'Vehicles')
@section('content')
<div class="row" style="justify-content:space-between;">
    <h1>Vehicles</h1>
    @if (auth('staff')->user()->canMutate())
        <a class="btn" href="{{ route('dealer.vehicles.create') }}">Add manual car</a>
    @endif
</div>
<form class="row" method="get" style="margin:1rem 0;">
    <input name="q" placeholder="Registration, make, model" value="{{ request('q') }}">
    <select name="status"><option value="">Any status</option>@foreach (['available','reserved','preparation','sold','hidden'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select>
    <select name="source"><option value="">Any source</option><option value="autotrader" @selected(request('source') === 'autotrader')>Autotrader</option><option value="manual" @selected(request('source') === 'manual')>Manual</option></select>
    <button class="btn" type="submit">Filter</button>
</form>
<div class="panel">
    <table>
        <tr><th>Car</th><th>Status</th><th>Source</th><th>Price</th><th></th></tr>
        @foreach ($cars as $car)
            <tr>
                <td>{{ $car->title() }}<br><span class="meta">{{ $car->registration }}</span></td>
                <td><span class="badge {{ $car->website_status }}">{{ $car->website_status }}</span>@unless($car->show_on_website)<br><span class="meta">Off the website</span>@endunless</td>
                <td>{{ $car->source }}</td>
                <td>@gbp($car->publicPricePence())</td>
                <td>
                    <a href="{{ route('dealer.vehicles.edit', $car) }}">Open</a>
                    @if (auth('staff')->user()->canMutate() && $car->source === 'manual' && ! $car->sales_exists)
                        <form method="post" action="{{ route('dealer.vehicles.destroy', $car) }}" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button class="btn danger" type="submit">Remove</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
</div>
{{ $cars->links() }}
@endsection
