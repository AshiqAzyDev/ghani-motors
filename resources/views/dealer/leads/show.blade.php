@extends('layouts.dealer')
@section('title', 'Lead')
@section('actions')
    <a class="btn smallbtn ghost" href="{{ route('dealer.leads') }}">Back to leads</a>
@endsection
@section('content')
<div class="settings-grid">
    <section class="panel">
        <h2>{{ $lead->subject }}</h2>
        <p class="meta">
            <span class="badge {{ $lead->status }}">{{ ucfirst($lead->status) }}</span>
            · {{ ucfirst(str_replace('_', ' ', $lead->type)) }}
            · {{ optional($lead->created_at)->format('j M Y H:i') }}
        </p>
        <div class="at-status" style="margin-top:1rem;">
            <div class="at-status-row"><span>Customer</span><strong>@if($lead->customer)<a href="{{ route('dealer.customers.show', $lead->customer) }}">{{ $lead->customer->name }}</a>@else — @endif</strong></div>
            <div class="at-status-row"><span>Phone</span><strong>{{ $lead->customer?->phone ?: '—' }}</strong></div>
            <div class="at-status-row"><span>Email</span><strong>{{ $lead->customer?->email ?: '—' }}</strong></div>
            <div class="at-status-row"><span>Vehicle</span><strong>@if($lead->vehicle)<a href="{{ route('cars.show', $lead->vehicle->slug) }}" target="_blank">{{ $lead->vehicle->title() }}</a>@else — @endif</strong></div>
            <div class="at-status-row"><span>Assigned</span><strong>{{ $lead->assignee?->name ?: '—' }}</strong></div>
        </div>
        @if ($lead->message)
            <h3 style="margin-top:1rem;">Original message</h3>
            <p>{{ $lead->message }}</p>
        @endif
        @if ($lead->valuation)
            @php($v = $lead->valuation)
            <h3 style="margin-top:1rem;">Valuation desk</h3>
            <div class="at-status">
                <div class="at-status-row"><span>Vehicle</span><strong>{{ $v->registration }} · {{ $v->make }} {{ $v->model }} · {{ $v->year }}</strong></div>
                <div class="at-status-row"><span>Mileage</span><strong>{{ number_format($v->mileage) }} miles</strong></div>
                <div class="at-status-row"><span>Fuel / gearbox</span><strong>{{ $v->fuel }} · {{ $v->transmission }}</strong></div>
                <div class="at-status-row"><span>Intent</span><strong>{{ ucfirst(str_replace('_', ' ', $v->intent)) }}</strong></div>
                <div class="at-status-row"><span>Condition</span><strong>{{ ucfirst(str_replace('_', ' ', $v->condition)) }}</strong></div>
                <div class="at-status-row"><span>Service history</span><strong>{{ ucfirst(str_replace('_', ' ', $v->service_history)) }}</strong></div>
                <div class="at-status-row"><span>MOT expiry</span><strong>{{ optional($v->mot_expiry)->format('j M Y') ?: '—' }}</strong></div>
                <div class="at-status-row"><span>Outstanding finance</span><strong>{{ ucfirst(str_replace('_', ' ', $v->outstanding_finance)) }}</strong></div>
                <div class="at-status-row"><span>Preferred contact</span><strong>{{ ucfirst($v->preferred_contact) }}</strong></div>
            </div>
            @if ($v->notes)
                <p style="margin-top:.75rem;">{{ $v->notes }}</p>
            @endif
            <div class="req-chips" style="margin-top:.75rem;">
                @foreach ($v->photos as $photo)
                    <a class="req-chip" href="{{ route('dealer.photos', $photo) }}" target="_blank">Photo {{ $loop->iteration }}</a>
                @endforeach
            </div>
            <p class="meta" style="margin-top:.75rem;">Use contact notes below for offers and next steps; close the lead when declined or completed.</p>
        @endif
    </section>

    <section class="panel">
        <h2>Status &amp; notes</h2>
        @if (auth('staff')->user()->can('leads.manage'))
            <form method="post" action="{{ route('dealer.leads.update', $lead) }}">
                @csrf
                <label>Status
                    <select name="status">
                        @foreach (['new','contacted','closed'] as $status)
                            <option value="{{ $status }}" @selected(old('status', $lead->status) === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Contact notes
                    <textarea name="contact_notes" rows="4" placeholder="What you discussed and the next step">{{ old('contact_notes', $lead->contact_notes) }}</textarea>
                </label>
                <label>Additional notes
                    <textarea name="additional_notes" rows="4" placeholder="Delivery, finance band, part exchange, other points">{{ old('additional_notes', $lead->additional_notes) }}</textarea>
                </label>
                <button class="btn" type="submit" style="margin-top:.75rem;">Save lead</button>
            </form>
        @else
            <p><strong>Status:</strong> {{ ucfirst($lead->status) }}</p>
            <p><strong>Contact notes:</strong> {{ $lead->contact_notes ?: '—' }}</p>
            <p><strong>Additional notes:</strong> {{ $lead->additional_notes ?: '—' }}</p>
        @endif
    </section>
</div>

<section class="panel" style="margin-top:1rem;">
    <h2>Buyer requirements</h2>
    <p class="meta">Main details that matter for matching stock — year, mileage, fuel, gearbox, colour and more.</p>
    <div class="req-chips">
        @forelse ($lead->requirementPairs() as $pair)
            <div class="req-chip">
                <strong>{{ $pair['label'] }}</strong>
                <span>{{ $pair['value'] }}</span>
                @if (auth('staff')->user()->can('leads.manage'))
                    <form method="post" action="{{ route('dealer.leads.requirements.update', [$lead, $pair['key']]) }}" class="req-edit">
                        @csrf
                        <input name="value" value="{{ $pair['value'] }}" aria-label="Edit {{ $pair['label'] }}">
                        <button class="rowbtn" type="submit">Save</button>
                    </form>
                    <form method="post" action="{{ route('dealer.leads.requirements.destroy', [$lead, $pair['key']]) }}">
                        @csrf
                        @method('DELETE')
                        <button class="rowbtn danger" type="submit">Remove</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="empty-row">No requirements yet.</p>
        @endforelse
    </div>
    @if (auth('staff')->user()->can('leads.manage'))
        <form method="post" action="{{ route('dealer.leads.requirements.store', $lead) }}" class="req-add" style="margin-top:1rem;">
            @csrf
            <div class="row">
                <select name="key" data-req-key>
                    @foreach ($requirementKeys as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                    <option value="custom">Custom…</option>
                </select>
                <input name="custom_key" placeholder="Custom label" data-req-custom style="display:none;">
                <input name="value" placeholder="Value e.g. Automatic / under 40k" required>
                <button class="btn smallbtn" type="submit">Add detail</button>
            </div>
        </form>
        <script>
            document.querySelector('[data-req-key]')?.addEventListener('change', (e) => {
                const custom = document.querySelector('[data-req-custom]');
                if (!custom) return;
                custom.style.display = e.target.value === 'custom' ? '' : 'none';
                custom.required = e.target.value === 'custom';
            });
        </script>
    @endif
</section>
@endsection
