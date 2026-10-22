@extends('layouts.dealer')
@section('title', 'Overview')
@section('content')
<div class="welcome">
    <div>
        <div class="eyebrow">Your dealership at a glance</div>
        <h2>Overview</h2>
        <p>From first enquiry to the next set of keys.</p>
    </div>
    @if (auth('staff')->user()->canMutate())
        <a class="btn" href="{{ route('dealer.vehicles.create') }}">Add vehicle</a>
    @endif
</div>
<div class="metrics">
    @foreach (['available' => 'Available', 'reserved' => 'Reserved', 'preparation' => 'Preparation', 'sold' => 'Sold'] as $key => $label)
        <div class="metric"><span>{{ $label }}</span><strong>{{ $counts[$key] ?? 0 }}</strong></div>
    @endforeach
    <div class="metric"><span>Stock retail value</span><strong>@gbp($stockValue)</strong></div>
    <div class="metric"><span>Recorded sales</span><strong>{{ $sales }}</strong></div>
    <div class="metric"><span>Outstanding invoices</span><strong>@gbp($outstanding)</strong></div>
</div>
<div class="panel" style="margin-top:1rem;">
    <h2>Phone</h2>
    <p>The website shows a WhatsApp button when this is a real number. The placeholder 020 0000 0000 stays hidden.</p>
    @if (auth('staff')->user()->canMutate())
        <form method="post" action="{{ route('dealer.phone') }}">
            @csrf
            <label>Dealership phone</label>
            <input name="phone" value="{{ old('phone', $dealer->phone) }}" required>
            <button class="btn" type="submit" style="margin-top:.6rem;">Save number</button>
        </form>
    @else
        <p>{{ $dealer->phone }}</p>
    @endif
</div>
<div class="panel" style="margin-top:1rem;">
    <h2>Sync health</h2>
    <p>Environment: {{ $dealer->integration->environment }}. Advertiser: {{ $dealer->advertiser_id ?: 'not set' }}. On integration: {{ $dealer->on_integration ? 'yes' : 'no' }}.</p>
    <p>Notifications {{ $dealer->integration->notifications_paused ? 'paused' : 'running' }}. Open API pauses: {{ $blocks }}. Failed webhooks: {{ $failedHooks }}.</p>
    <p>Last sync: {{ $lastSync ? $lastSync->kind.' '.$lastSync->status.' at '.$lastSync->created_at : 'none yet' }}.</p>
    @if (auth('staff')->user()->isAdmin() && auth('staff')->user()->canMutate())
        <form method="post" action="{{ route('dealer.sync') }}">@csrf<button class="btn" type="submit">Run Autotrader baseline</button></form>
    @endif
</div>
<div class="panel" style="margin-top:1rem;">
    <h2>Recent activity</h2>
    @forelse ($activity as $row)
        <p>{{ $row->created_at }} · {{ $row->message }}</p>
    @empty
        <p>No activity yet.</p>
    @endforelse
</div>
@endsection
