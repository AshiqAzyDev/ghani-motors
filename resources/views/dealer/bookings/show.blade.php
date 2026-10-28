@extends('layouts.dealer')
@section('title', 'Booking')
@section('actions')
    <a class="btn smallbtn ghost" href="{{ route('dealer.bookings') }}">Back to bookings</a>
    @if ($booking->customer)
        <a class="btn smallbtn ghost" href="{{ route('dealer.customers.show', $booking->customer) }}">Open customer</a>
    @endif
@endsection
@section('content')
@php
    $vehicle = $booking->vehicle;
    $customer = $booking->customer;
    $image = $vehicle?->images?->first();
    $price = $vehicle?->publicPricePence();
    $typeLabel = ucfirst(str_replace('_', ' ', $booking->type));
    $statusLabel = ucfirst($booking->status);
    $timeLabel = $booking->preferred_time ? \Illuminate\Support\Str::of($booking->preferred_time)->substr(0, 5) : null;
    $days = $booking->preferred_on ? (int) now()->startOfDay()->diffInDays($booking->preferred_on->copy()->startOfDay(), false) : null;
    $countdown = match (true) {
        $days === null => null,
        $days > 1 => "Upcoming in {$days} days",
        $days === 1 => 'Upcoming tomorrow',
        $days === 0 => 'Scheduled for today',
        $days === -1 => 'Was yesterday',
        default => 'Past appointment',
    };
    $initials = collect(preg_split('/\s+/', trim((string) ($customer?->name ?? ''))))
        ->filter()
        ->take(2)
        ->map(fn ($part) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))
        ->implode('') ?: '?';
@endphp
<div class="hub-page">
    <section class="hub-banner">
        <div>
            <div class="hub-banner-title">
                <h2>Booking Details</h2>
                <span class="hub-pill hub-pill-type">{{ $typeLabel }}</span>
                <span class="hub-pill hub-pill-status {{ $booking->status }}">
                    <span class="hub-pill-dot" aria-hidden="true"></span>
                    {{ $statusLabel }}
                </span>
            </div>
            <div class="hub-banner-meta">
                <span>Scheduled:</span>
                <strong>{{ optional($booking->preferred_on)->format('j M Y') ?: '—' }}</strong>
                @if ($timeLabel)
                    <span class="sep">•</span>
                    <strong>{{ $timeLabel }}</strong>
                @endif
            </div>
        </div>
        @if ($countdown)
            <div class="hub-countdown {{ $days !== null && $days < 0 ? 'is-past' : '' }}">
                <i aria-hidden="true"></i>
                <span>Appointment status: <b>{{ $countdown }}</b></span>
            </div>
        @endif
    </section>

    <div class="hub-grid">
        <div class="hub-main">
            <article class="hub-card">
                <div class="hub-card-head">
                    <span>Selected Vehicle</span>
                    @if ($vehicle?->slug)
                        <a class="hub-card-link" href="{{ route('cars.show', $vehicle->slug) }}" target="_blank" rel="noopener">View stock item →</a>
                    @endif
                </div>
                <div class="hub-vehicle-body">
                    @if ($image)
                        <div class="hub-vehicle-thumb">
                            <img src="{{ $image->sized('w400h300') }}" alt="{{ $vehicle?->title() }}">
                            <span class="hub-thumb-label">{{ ucfirst($vehicle?->website_status ?: 'Stock') }}</span>
                        </div>
                    @else
                        <div class="hub-vehicle-thumb is-empty">No photo</div>
                    @endif
                    <div class="hub-vehicle-copy">
                        <div class="hub-vehicle-top">
                            <div>
                                <h3>{{ $vehicle?->title() ?: '—' }}</h3>
                                <p class="hub-sub">
                                    {{ collect([$vehicle?->body, $vehicle?->fuel, $vehicle?->transmission])->filter()->implode(' · ') ?: 'Vehicle details' }}
                                </p>
                            </div>
                            @if ($price !== null)
                                <div class="hub-price">@gbp($price)</div>
                            @endif
                        </div>
                        <div class="hub-specs">
                            @if ($vehicle?->registration)
                                <span class="hub-reg">{{ $vehicle->registration }}</span>
                            @endif
                            @if ($vehicle?->mileage !== null)
                                <span class="hub-chip">{{ number_format($vehicle->mileage) }} mi</span>
                            @endif
                            @if ($vehicle?->fuel)
                                <span class="hub-chip">{{ $vehicle->fuel }}</span>
                            @endif
                            @if ($vehicle?->transmission)
                                <span class="hub-chip">{{ $vehicle->transmission }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </article>

            <article class="hub-card">
                <div class="hub-card-head">
                    <span>Customer &amp; Contact Information</span>
                    @if ($customer)
                        <a class="hub-card-link" href="{{ route('dealer.customers.show', $customer) }}">Open full profile →</a>
                    @endif
                </div>
                <div class="hub-customer-grid">
                    <div class="hub-customer-profile">
                        <div class="hub-avatar" aria-hidden="true">{{ $initials }}</div>
                        <div>
                            <h4>{{ $customer?->name ?: '—' }}</h4>
                            <p class="hub-sub">{{ $customer?->email ?: 'No email on file' }}</p>
                        </div>
                    </div>
                    <div class="hub-contact">
                        <div class="hub-contact-row">
                            <span>Email:
                                @if ($customer?->email)
                                    <a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a>
                                @else
                                    —
                                @endif
                            </span>
                        </div>
                        <div class="hub-contact-row">
                            <span>Phone:
                                @if ($customer?->phone)
                                    <a href="tel:{{ preg_replace('/\s+/', '', $customer->phone) }}">{{ $customer->phone }}</a>
                                @else
                                    —
                                @endif
                            </span>
                            @if ($customer?->phone)
                                <a class="hub-call" href="tel:{{ preg_replace('/\s+/', '', $customer->phone) }}">Call now</a>
                            @endif
                        </div>
                    </div>
                </div>
                @if ($booking->notes)
                    <div class="hub-notes">
                        <span>Internal appointment notes</span>
                        <div class="hub-notes-box">{{ $booking->notes }}</div>
                    </div>
                @endif
            </article>
        </div>

        <aside class="hub-card hub-actions">
            <h3>Actions <small>Workflow</small></h3>
            <div class="hub-actions-stack">
                @if (auth('staff')->user()->can('bookings.manage'))
                    @forelse ($booking->nextStatuses() as $next)
                        <form method="post" action="{{ route('dealer.bookings.status', $booking) }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ $next }}">
                            <button
                                class="hub-btn {{ $next === 'cancelled' ? 'hub-btn-danger' : ($next === 'completed' || $next === 'confirmed' ? 'hub-btn-primary' : 'hub-btn-ghost') }}"
                                type="submit"
                            >Mark {{ ucfirst($next) }}</button>
                        </form>
                    @empty
                        <p class="meta">No status changes available.</p>
                    @endforelse
                @else
                    <p class="meta">View only — ask an admin if you need to update this booking.</p>
                @endif
            </div>
            <div class="hub-actions-foot">
                <a href="{{ route('dealer.bookings') }}">← Back to bookings list</a>
            </div>
        </aside>
    </div>

    <section class="hub-related">
        <div class="hub-related-head">
            <h3>Related History &amp; Activities</h3>
            <p>Connected leads, other bookings, sales and invoices for this customer.</p>
        </div>
        <div class="hub-related-grid">
            <div class="hub-card hub-related-card">
                <div class="hub-card-head">
                    <span><i class="hub-dot hub-dot-sky" aria-hidden="true"></i> Leads</span>
                    <span class="hub-related-count">{{ $leads->count() }}</span>
                </div>
                <div class="hub-related-list">
                    @forelse ($leads as $lead)
                        <div class="hub-related-item">
                            <div>
                                <b><a href="{{ route('dealer.leads.show', $lead) }}">{{ $lead->subject }}</a></b>
                                <p class="hub-sub">{{ ucfirst(str_replace('_', ' ', $lead->type)) }} · {{ $lead->vehicle?->title() }}</p>
                            </div>
                            <div class="hub-item-side">
                                <span class="hub-pill hub-pill-status {{ $lead->status }}">{{ ucfirst($lead->status) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="hub-empty">
                            <p>No leads yet.</p>
                            <small>Enquiries linked to this customer will appear here.</small>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="hub-card hub-related-card">
                <div class="hub-card-head">
                    <span><i class="hub-dot hub-dot-amber" aria-hidden="true"></i> Other bookings</span>
                    <span class="hub-related-count">{{ $otherBookings->count() }}</span>
                </div>
                <div class="hub-related-list">
                    @forelse ($otherBookings as $other)
                        <div class="hub-related-item">
                            <div>
                                <b><a href="{{ route('dealer.bookings.show', $other) }}">{{ ucfirst(str_replace('_', ' ', $other->type)) }}: {{ $other->vehicle?->title() }}</a></b>
                                <p class="hub-sub">{{ optional($other->preferred_on)->format('j M Y') }}</p>
                            </div>
                            <div class="hub-item-side">
                                <span class="hub-pill hub-pill-status {{ $other->status }}">{{ ucfirst($other->status) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="hub-empty">
                            <p>No other bookings yet.</p>
                            <small>Earlier appointments for this customer show here.</small>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="hub-card hub-related-card">
                <div class="hub-card-head">
                    <span><i class="hub-dot hub-dot-emerald" aria-hidden="true"></i> Sales</span>
                    <span class="hub-related-count">{{ $sales->count() }}</span>
                </div>
                <div class="hub-related-list">
                    @forelse ($sales as $sale)
                        <div class="hub-related-item">
                            <div>
                                <b><a href="{{ route('dealer.sales.show', $sale) }}">{{ $sale->vehicle?->title() ?: $sale->vehicle_label }}</a></b>
                                <p class="hub-sub">{{ optional($sale->sold_on)->format('j M Y') }} · @gbp($sale->price_pence)</p>
                            </div>
                        </div>
                    @empty
                        <div class="hub-empty">
                            <p>No sales yet.</p>
                            <small>Completed sales for this customer will appear here.</small>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="hub-card hub-related-card">
                <div class="hub-card-head">
                    <span><i class="hub-dot hub-dot-indigo" aria-hidden="true"></i> Invoices</span>
                    <span class="hub-related-count">{{ $invoices->count() }}</span>
                </div>
                <div class="hub-related-list">
                    @forelse ($invoices as $invoice)
                        <div class="hub-related-item">
                            <div>
                                <b>
                                    @if (auth('staff')->user()->can('invoices.view'))
                                        <a href="{{ route('dealer.invoices.show', $invoice) }}">{{ $invoice->number }}</a>
                                    @else
                                        {{ $invoice->number }}
                                    @endif
                                </b>
                                <p class="hub-sub">@gbp($invoice->total_pence)</p>
                            </div>
                            <div class="hub-item-side">
                                <span class="hub-pill hub-pill-status {{ $invoice->status }}">{{ ucfirst(str_replace('_', ' ', $invoice->status)) }}</span>
                                @if (auth('staff')->user()->can('invoices.view'))
                                    <a class="hub-card-link" href="{{ route('dealer.invoices.print', $invoice) }}" target="_blank" rel="noopener">Print</a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="hub-empty">
                            <p>No invoices yet.</p>
                            <small>Billing documents for this customer show here.</small>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
