@extends('layouts.dealer')
@section('title', 'Overview')
@section('actions')
    @if (auth('staff')->user()->isAdmin())
        <a class="btn smallbtn ghost" href="{{ route('dealer.settings') }}">Settings</a>
    @endif
    @if (auth('staff')->user()->can('vehicles.edit_manual'))
        <a class="btn smallbtn" href="{{ route('dealer.vehicles.create') }}">Add manual car</a>
    @endif
@endsection
@section('content')
<div class="welcome dash-welcome">
    <div>
        <h2>Welcome back{{ auth('staff')->user()->name ? ', '.auth('staff')->user()->name : '' }}</h2>
        <p class="page-note" style="margin:0;">Stock, leads, bookings and sales at a glance.</p>
    </div>
    @if (auth('staff')->user()->isAdmin())
        <div class="dash-sync">
            <span class="at-pill {{ ($lastSync?->status === 'succeeded') ? 'ok' : (($lastSync?->status === 'failed') ? 'bad' : 'unknown') }}">
                Last sync: {{ $lastSync ? $lastSync->kind.' · '.$lastSync->status : 'none yet' }}
            </span>
        </div>
    @endif
</div>

<div class="metrics dash-metrics">
    @if (auth('staff')->user()->can('vehicles.view'))
        <div class="metric"><span>Available stock</span><strong>{{ $counts['available'] ?? 0 }}</strong></div>
        <div class="metric"><span>Reserved</span><strong>{{ $counts['reserved'] ?? 0 }}</strong></div>
        <div class="metric"><span>Stock value</span><strong>@gbp($stockValue)</strong></div>
    @endif
    @if (auth('staff')->user()->can('leads.view'))
        <div class="metric"><span>New leads</span><strong>{{ $newLeads }}</strong></div>
    @endif
    @if (auth('staff')->user()->can('bookings.view'))
        <div class="metric"><span>Open bookings</span><strong>{{ $openBookings }}</strong></div>
    @endif
    @if (auth('staff')->user()->can('sales.view'))
        <div class="metric"><span>Sales this month</span><strong>@gbp($salesThisMonth)</strong></div>
        <div class="metric"><span>Completed sales</span><strong>{{ $salesCount }}</strong></div>
    @endif
    @if (auth('staff')->user()->can('invoices.view'))
        <div class="metric"><span>Outstanding invoices</span><strong>@gbp($outstanding)</strong></div>
    @endif
</div>

@if (
    (auth('staff')->user()->can('leads.view') && $attention['new_leads'])
    || (auth('staff')->user()->can('bookings.view') && $attention['open_bookings'])
    || (auth('staff')->user()->can('invoices.view') && $attention['outstanding'] > 0)
    || (auth('staff')->user()->isAdmin() && ($attention['failed_hooks'] || ! $attention['whatsapp_live'] || ! $attention['webhook_set']))
)
    <div class="dash-alerts panel">
        <h2>Needs attention</h2>
        <ul>
            @if (auth('staff')->user()->can('leads.view') && $attention['new_leads'])
                <li><a href="{{ route('dealer.leads', ['status' => 'new']) }}">{{ $attention['new_leads'] }} new lead{{ $attention['new_leads'] === 1 ? '' : 's' }} waiting</a></li>
            @endif
            @if (auth('staff')->user()->can('bookings.view') && $attention['open_bookings'])
                <li><a href="{{ route('dealer.bookings') }}">{{ $attention['open_bookings'] }} open booking{{ $attention['open_bookings'] === 1 ? '' : 's' }}</a></li>
            @endif
            @if (auth('staff')->user()->can('invoices.view') && $attention['outstanding'] > 0)
                <li><a href="{{ route('dealer.invoices') }}">Outstanding invoices: @gbp($attention['outstanding'])</a></li>
            @endif
            @if (auth('staff')->user()->isAdmin() && $attention['failed_hooks'])
                <li><a href="{{ route('dealer.settings') }}">{{ $attention['failed_hooks'] }} failed webhook{{ $attention['failed_hooks'] === 1 ? '' : 's' }}</a></li>
            @endif
            @if (auth('staff')->user()->isAdmin() && ! $attention['whatsapp_live'])
                <li><a href="{{ route('dealer.settings') }}">Set a real phone number to show WhatsApp on the website</a></li>
            @endif
            @if (auth('staff')->user()->isAdmin() && ! $attention['webhook_set'])
                <li><a href="{{ route('dealer.settings') }}">Webhook secret is not set — live Autotrader updates are blocked</a></li>
            @endif
        </ul>
    </div>
@endif

<div class="dashboard-grid dash-charts">
    @if (auth('staff')->user()->can('vehicles.view'))
        <section class="panel chart-panel">
            <h2>Stock by status</h2>
            <canvas id="chart-stock" height="220" aria-label="Stock status chart"></canvas>
        </section>
    @endif
    @if (auth('staff')->user()->can('leads.view'))
        <section class="panel chart-panel">
            <h2>Leads · last 14 days</h2>
            <canvas id="chart-leads" height="220" aria-label="Leads chart"></canvas>
        </section>
    @endif
    @if (auth('staff')->user()->can('sales.view'))
        <section class="panel chart-panel">
            <h2>Sales value · 6 months</h2>
            <canvas id="chart-sales" height="220" aria-label="Sales chart"></canvas>
        </section>
    @endif
    @if (auth('staff')->user()->can('bookings.view'))
        <section class="panel chart-panel">
            <h2>Open bookings by type</h2>
            <canvas id="chart-bookings" height="220" aria-label="Bookings chart"></canvas>
        </section>
    @endif
</div>

<div class="dashboard-grid" style="margin-top:1rem;">
    @if (auth('staff')->user()->can('leads.view'))
        <section class="panel">
            <div class="dsectionhead">
                <h2>Recent leads</h2>
                <a class="textlink" href="{{ route('dealer.leads') }}">View all</a>
            </div>
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Customer</th><th>Type</th><th>Status</th><th>When</th></tr></thead>
                    <tbody>
                        @forelse ($recentLeads as $lead)
                            <tr class="is-clickable" onclick="window.location='{{ route('dealer.leads.show', $lead) }}'" style="cursor:pointer;">
                                <td><a href="{{ route('dealer.leads.show', $lead) }}">{{ $lead->customer?->name ?? '—' }}</a></td>
                                <td>{{ str_replace('_', ' ', $lead->type) }}</td>
                                <td><span class="badge {{ $lead->status }}">{{ $lead->status }}</span></td>
                                <td>{{ optional($lead->created_at)->timezone(config('app.timezone'))->format('j M H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-row">No leads yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
    @if (auth('staff')->user()->can('bookings.view'))
        <section class="panel">
            <div class="dsectionhead">
                <h2>Upcoming bookings</h2>
                <a class="textlink" href="{{ route('dealer.bookings') }}">View all</a>
            </div>
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Customer</th><th>Type</th><th>When</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($upcomingBookings as $booking)
                            <tr class="is-clickable" onclick="window.location='{{ route('dealer.bookings') }}'" style="cursor:pointer;">
                                <td>
                                    @if($booking->customer && auth('staff')->user()->can('customers.view'))
                                        <a href="{{ route('dealer.customers.show', $booking->customer) }}">{{ $booking->customer->name }}</a>
                                    @else
                                        {{ $booking->customer?->name ?? '—' }}
                                    @endif
                                </td>
                                <td>{{ str_replace('_', ' ', $booking->type) }}</td>
                                <td>
                                    {{ optional($booking->preferred_on)->format('j M Y') }}
                                    @if ($booking->preferred_time) · {{ \Illuminate\Support\Str::of($booking->preferred_time)->substr(0, 5) }}@endif
                                </td>
                                <td><span class="badge {{ $booking->status }}">{{ $booking->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-row">No open bookings.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>

<section class="panel" style="margin-top:1rem;">
    <div class="dsectionhead">
        <h2>Recent activity</h2>
        @if (auth('staff')->user()->can('customers.view'))
            <span class="meta">{{ $customersTotal }} customers · {{ $customersMonth }} new this month</span>
        @endif
    </div>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr><th class="sno">S.no</th><th>When</th><th>Activity</th></tr>
            </thead>
            <tbody>
                @forelse ($activity as $row)
                    <tr>
                        <td class="sno">{{ $loop->iteration }}</td>
                        <td>{{ $row->created_at?->timezone(config('app.timezone'))->format('j M Y H:i') }}</td>
                        <td>{{ $row->message }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty-row">No activity yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@if (auth('staff')->user()->can('vehicles.view') || auth('staff')->user()->can('leads.view') || auth('staff')->user()->can('sales.view') || auth('staff')->user()->can('bookings.view'))
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (!window.Chart) return;
    const charts = @json($charts);
    const teal = '#00a0d0';
    const ink = '#101616';
    const muted = ['#00a0d0', '#0b192c', '#64748b', '#94a3b8', '#25d366', '#f59e0b'];

    const stockEl = document.getElementById('chart-stock');
    if (stockEl) {
        new Chart(stockEl, {
            type: 'doughnut',
            data: {
                labels: charts.stock.labels,
                datasets: [{ data: charts.stock.values, backgroundColor: muted, borderWidth: 0 }]
            },
            options: { plugins: { legend: { position: 'bottom' } }, cutout: '58%' }
        });
    }

    const leadsEl = document.getElementById('chart-leads');
    if (leadsEl) {
        new Chart(leadsEl, {
            type: 'line',
            data: {
                labels: charts.leads.labels,
                datasets: [{
                    label: 'Leads',
                    data: charts.leads.values,
                    borderColor: teal,
                    backgroundColor: 'rgba(0,160,208,.15)',
                    fill: true,
                    tension: .35,
                    pointRadius: 3,
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    const salesEl = document.getElementById('chart-sales');
    if (salesEl) {
        new Chart(salesEl, {
            type: 'bar',
            data: {
                labels: charts.sales.labels,
                datasets: [{
                    label: '£',
                    data: charts.sales.values,
                    backgroundColor: ink,
                    borderRadius: 6,
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    const bookingsEl = document.getElementById('chart-bookings');
    if (bookingsEl) {
        new Chart(bookingsEl, {
            type: 'bar',
            data: {
                labels: charts.bookings.labels,
                datasets: [{
                    data: charts.bookings.values,
                    backgroundColor: [teal, ink, '#64748b'],
                    borderRadius: 6,
                }]
            },
            options: {
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }
});
</script>
@endif
@endsection
