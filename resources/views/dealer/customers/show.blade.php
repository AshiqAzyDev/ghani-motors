@extends('layouts.dealer')
@section('title', $customer->name)
@section('actions')
    <a class="btn smallbtn ghost" href="{{ route('dealer.customers') }}">Back to customers</a>
    @if (auth('staff')->user()->can('customers.view'))
        <a class="btn smallbtn" href="{{ route('dealer.export', 'customers') }}">Export CSV</a>
    @endif
@endsection
@section('content')
<div class="settings-grid">
    <section class="panel">
        <h2>Customer</h2>
        <div class="at-status">
            <div class="at-status-row"><span>Email</span><strong>{{ $customer->email }}</strong></div>
            <div class="at-status-row"><span>Phone</span><strong>{{ $customer->phone ?: '—' }}</strong></div>
            <div class="at-status-row"><span>Postcode</span><strong>{{ $customer->postcode ?: '—' }}</strong></div>
            <div class="at-status-row"><span>Source</span><strong>{{ ucfirst(str_replace('_', ' ', $customer->source)) }}</strong></div>
        </div>
        @if (auth('staff')->user()->can('customers.manage'))
            <form method="post" action="{{ route('dealer.customers.update', $customer) }}" style="margin-top:1rem;">
                @csrf
                @method('PUT')
                <label>Name<input name="name" value="{{ old('name', $customer->name) }}" required></label>
                <label>Phone<input name="phone" value="{{ old('phone', $customer->phone) }}" required></label>
                <label>Postcode<input name="postcode" value="{{ old('postcode', $customer->postcode) }}"></label>
                <label>Address<input name="address" value="{{ old('address', $customer->address) }}"></label>
                <label>Source
                    <select name="source">
                        @foreach (['showroom','phone','website_enquiry','finance_enquiry','sell_your_car','other'] as $source)
                            <option value="{{ $source }}" @selected(old('source', $customer->source) === $source)>{{ str_replace('_',' ', $source) }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Notes<textarea name="notes" rows="4">{{ old('notes', $customer->notes) }}</textarea></label>
                <button class="btn" type="submit" style="margin-top:.75rem;">Save customer</button>
            </form>
        @else
            <p style="margin-top:1rem;">{{ $customer->notes ?: 'No notes.' }}</p>
        @endif
    </section>

    <section class="panel">
        <h2>Timeline</h2>
        @forelse ($activity as $row)
            <div class="account-line">
                <div>
                    <b>{{ $row->message }}</b>
                    <p class="meta">{{ optional($row->created_at)->format('j M Y H:i') }}</p>
                </div>
            </div>
        @empty
            <p class="empty-row">No activity logged yet.</p>
        @endforelse
    </section>
</div>

<div class="settings-grid" style="margin-top:1rem;">
    <section class="panel">
        <h2>Leads &amp; valuations</h2>
        @forelse ($customer->leads as $lead)
            <div class="account-line">
                <div>
                    <b><a href="{{ route('dealer.leads.show', $lead) }}">{{ $lead->subject }}</a></b>
                    <p class="meta">
                        {{ ucfirst(str_replace('_', ' ', $lead->type)) }}
                        @if ($lead->valuation)
                            · Valuation {{ $lead->valuation->registration }} {{ $lead->valuation->make }} {{ $lead->valuation->model }}
                        @endif
                    </p>
                </div>
                <span class="badge {{ $lead->status }}">{{ ucfirst($lead->status) }}</span>
            </div>
        @empty
            <p class="empty-row">No leads.</p>
        @endforelse
    </section>

    <section class="panel">
        <h2>Bookings</h2>
        @forelse ($customer->bookings as $booking)
            <div class="account-line">
                <div>
                    <b><a href="{{ route('dealer.bookings.show', $booking) }}">{{ ucfirst(str_replace('_', ' ', $booking->type)) }}</a></b>
                    <p class="meta">{{ $booking->vehicle?->title() }} · {{ optional($booking->preferred_on)->format('j M Y') }}</p>
                </div>
                <span class="badge {{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
            </div>
        @empty
            <p class="empty-row">No bookings.</p>
        @endforelse
        <h3 style="margin-top:1rem;">Sales</h3>
        @forelse ($sales as $sale)
            <div class="account-line">
                <div>
                    <b><a href="{{ route('dealer.sales.show', $sale) }}">{{ $sale->vehicle?->title() ?: $sale->vehicle_label }}</a></b>
                    <p class="meta">{{ optional($sale->sold_on)->format('j M Y') }} · @gbp($sale->price_pence)</p>
                </div>
            </div>
        @empty
            <p class="empty-row">No sales.</p>
        @endforelse
        <h3 style="margin-top:1rem;">Invoices</h3>
        @forelse ($invoices as $invoice)
            <div class="account-line">
                <div>
                    <b><a href="{{ route('dealer.invoices.show', $invoice) }}">{{ $invoice->number }}</a></b>
                    <p class="meta">@gbp($invoice->total_pence)</p>
                </div>
                <span class="badge {{ $invoice->status }}">{{ ucfirst(str_replace('_', ' ', $invoice->status)) }}</span>
            </div>
        @empty
            <p class="empty-row">No invoices.</p>
        @endforelse
    </section>
</div>
@endsection
