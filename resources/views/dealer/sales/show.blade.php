@extends('layouts.dealer')
@section('title', 'Sale')
@section('actions')
    <a class="btn smallbtn ghost" href="{{ route('dealer.sales') }}">Back to sales</a>
    @if ($sale->customer)
        <a class="btn smallbtn ghost" href="{{ route('dealer.customers.show', $sale->customer) }}">Open customer</a>
    @endif
@endsection
@section('content')
@php
    $vehicle = $sale->vehicle;
    $customer = $sale->customer;
    $image = $vehicle?->images?->first();
    $title = $vehicle?->title() ?: $sale->vehicle_label;
    $registration = $sale->registration ?: $vehicle?->registration;
    $statusLabel = ucfirst($sale->status);
    $methodLabel = ucfirst(str_replace('_', ' ', $sale->method));
    $initials = collect(preg_split('/\s+/', trim((string) ($customer?->name ?? ''))))
        ->filter()
        ->take(2)
        ->map(fn ($part) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))
        ->implode('') ?: '?';
    $px = $sale->partExchangeValuation;
@endphp
<div class="hub-page">
    <section class="hub-banner">
        <div>
            <div class="hub-banner-title">
                <h2>Sale Details</h2>
                <span class="hub-pill hub-pill-status {{ $sale->status }}">
                    <span class="hub-pill-dot" aria-hidden="true"></span>
                    {{ $statusLabel }}
                </span>
            </div>
            <div class="hub-banner-meta">
                <span>Sold:</span>
                <strong>{{ optional($sale->sold_on)->format('j M Y') ?: '—' }}</strong>
                <span class="sep">•</span>
                <strong>{{ $methodLabel }}</strong>
                <span class="sep">•</span>
                <strong>@gbp($sale->price_pence)</strong>
            </div>
        </div>
        <div class="hub-countdown {{ $sale->status === 'completed' ? '' : 'is-past' }}">
            <i aria-hidden="true"></i>
            <span>Sale status: <b>{{ $statusLabel }}</b></span>
        </div>
    </section>

    <div class="hub-grid">
        <div class="hub-main">
            <article class="hub-card">
                <div class="hub-card-head">
                    <span>Sold Vehicle</span>
                    @if ($vehicle?->slug)
                        <a class="hub-card-link" href="{{ route('cars.show', $vehicle->slug) }}" target="_blank" rel="noopener">View stock item →</a>
                    @endif
                </div>
                <div class="hub-vehicle-body">
                    @if ($image)
                        <div class="hub-vehicle-thumb">
                            <img src="{{ $image->sized('w400h300') }}" alt="{{ $title }}">
                            <span class="hub-thumb-label">Sold</span>
                        </div>
                    @else
                        <div class="hub-vehicle-thumb is-empty">No photo</div>
                    @endif
                    <div class="hub-vehicle-copy">
                        <div class="hub-vehicle-top">
                            <div>
                                <h3>{{ $title }}</h3>
                                <p class="hub-sub">
                                    {{ collect([$vehicle?->body, $vehicle?->fuel, $vehicle?->transmission])->filter()->implode(' · ') ?: 'Vehicle details' }}
                                </p>
                            </div>
                            <div class="hub-price">@gbp($sale->price_pence)</div>
                        </div>
                        <div class="hub-specs">
                            @if ($registration)
                                <span class="hub-reg">{{ $registration }}</span>
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
                            <span class="hub-chip">{{ $methodLabel }}</span>
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
                @if ($px)
                    <div class="hub-notes">
                        <span>Part exchange</span>
                        <div class="hub-notes-box">{{ $px->registration }} · {{ $px->make }} {{ $px->model }}</div>
                    </div>
                @endif
                @if ($sale->notes)
                    <div class="hub-notes">
                        <span>Sale notes</span>
                        <div class="hub-notes-box">{{ $sale->notes }}</div>
                    </div>
                @endif
            </article>
        </div>

        <aside class="hub-card hub-actions">
            <h3>Actions <small>Documents</small></h3>
            <div class="hub-actions-stack">
                @if ($customer)
                    <a class="hub-btn hub-btn-secondary" href="{{ route('dealer.customers.show', $customer) }}">Open customer</a>
                @endif
                @if ($sale->invoice && auth('staff')->user()->can('invoices.view'))
                    <a class="hub-btn hub-btn-primary" href="{{ route('dealer.invoices.show', $sale->invoice) }}">Open invoice {{ $sale->invoice->number }}</a>
                    <a class="hub-btn hub-btn-ghost" href="{{ route('dealer.invoices.print', $sale->invoice) }}" target="_blank" rel="noopener">Print invoice</a>
                @elseif (! $sale->invoice)
                    <p class="meta">No invoice linked to this sale yet.</p>
                @endif
            </div>
            <div class="hub-actions-foot">
                <a href="{{ route('dealer.sales') }}">← Back to sales list</a>
            </div>
        </aside>
    </div>

    <section class="hub-related">
        <div class="hub-related-head">
            <h3>Related History &amp; Activities</h3>
            <p>Connected leads, bookings, other sales and invoices for this customer.</p>
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
                    <span><i class="hub-dot hub-dot-amber" aria-hidden="true"></i> Bookings</span>
                    <span class="hub-related-count">{{ $bookings->count() }}</span>
                </div>
                <div class="hub-related-list">
                    @forelse ($bookings as $booking)
                        <div class="hub-related-item">
                            <div>
                                <b><a href="{{ route('dealer.bookings.show', $booking) }}">{{ ucfirst(str_replace('_', ' ', $booking->type)) }}: {{ $booking->vehicle?->title() }}</a></b>
                                <p class="hub-sub">{{ optional($booking->preferred_on)->format('j M Y') }}</p>
                            </div>
                            <div class="hub-item-side">
                                <span class="hub-pill hub-pill-status {{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="hub-empty">
                            <p>No bookings yet.</p>
                            <small>Appointments for this customer show here.</small>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="hub-card hub-related-card">
                <div class="hub-card-head">
                    <span><i class="hub-dot hub-dot-emerald" aria-hidden="true"></i> Other sales</span>
                    <span class="hub-related-count">{{ $otherSales->count() }}</span>
                </div>
                <div class="hub-related-list">
                    @forelse ($otherSales as $other)
                        <div class="hub-related-item">
                            <div>
                                <b><a href="{{ route('dealer.sales.show', $other) }}">{{ $other->vehicle?->title() ?: $other->vehicle_label }}</a></b>
                                <p class="hub-sub">{{ optional($other->sold_on)->format('j M Y') }} · @gbp($other->price_pence)</p>
                            </div>
                            <div class="hub-item-side">
                                <span class="hub-pill hub-pill-status {{ $other->status }}">{{ ucfirst($other->status) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="hub-empty">
                            <p>No other sales yet.</p>
                            <small>Earlier sales for this customer show here.</small>
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
