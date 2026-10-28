@php
    $customer = $invoice->customer_snapshot ?? [];
    $vehicle = $invoice->vehicle_snapshot ?? [];
    $customerName = trim((string) ($customer['name'] ?? '')) ?: 'Customer';
    $paidPence = (int) $invoice->payments->sum('amount_pence');
    $balancePence = (int) $invoice->total_pence - $paidPence;
    $isPaidInFull = $invoice->status === 'paid'
        || ($balancePence <= 0 && $paidPence > 0 && (int) $invoice->total_pence > 0);
    $statusLabel = ucfirst(str_replace('_', ' ', $invoice->status));
    $kindLabel = $invoice->kind === 'credit_note' ? 'Credit Note' : 'Sales Invoice & Receipt';
    $schemeLabel = ucfirst(str_replace('_', ' ', $invoice->vat_scheme));
    $issueDate = optional($invoice->issued_at ?? $invoice->created_at)->format('j M Y');
    $signDate = optional($invoice->issued_at ?? $invoice->created_at)->format('d/m/Y');
    $dealerName = $dealer->displayName();
    $authorisedName = $invoice->creator?->name ?: $dealerName;
    $authorisedRole = $invoice->creator
        ? ucfirst((string) $invoice->creator->role).' · '.$dealerName
        : 'Authorized representative · '.$dealerName;
    $lastPayment = $invoice->payments->sortByDesc('id')->first();
@endphp
<article class="inv-sheet">
    <div class="inv-brand">
        <div class="inv-brand-left">
            <p class="inv-wordmark">GHANI <span>MOTORS</span></p>
            <div class="inv-badges">
                <span class="inv-badge-aa">AA Approved Dealer</span>
                <span class="inv-badge-muted">{{ $dealer->displayTagline() }}</span>
            </div>
            <div class="inv-dealer-meta">
                <p class="inv-dealer-name">{{ $dealerName }}</p>
                <p>{{ $dealer->showroomAddress() }}</p>
                <p>
                    @if ($dealer->phone)Phone: {{ $dealer->phone }}@endif
                    @if ($dealer->phone && $dealer->publicEmail()) · @endif
                    @if ($dealer->publicEmail()){{ $dealer->publicEmail() }}@endif
                </p>
            </div>
        </div>
        <div class="inv-brand-right">
            <span class="inv-kind">{{ $kindLabel }}</span>
            <p class="inv-number">#{{ $invoice->number }}</p>
            <p class="inv-issue">Issue Date: <strong>{{ $issueDate ?: '—' }}</strong></p>
            @if ($isPaidInFull)
                <div class="inv-stamp inv-stamp-paid">
                    <strong>Paid in full</strong>
                    @if ($lastPayment)
                        <span>{{ optional($lastPayment->received_on)->format('j M Y') }}@if($lastPayment->reference) · Ref: {{ $lastPayment->reference }}@endif</span>
                    @else
                        <span>{{ $statusLabel }}</span>
                    @endif
                </div>
            @else
                <div class="inv-stamp inv-stamp-status {{ $invoice->status }}">
                    <strong>{{ $statusLabel }}</strong>
                    @if ($balancePence > 0)
                        <span>Balance @gbp($balancePence)</span>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <section class="inv-parties" aria-label="Customer and vehicle information">
        <div class="inv-card">
            <div class="inv-card-head">
                <h2>Invoice To (Customer)</h2>
            </div>
            <p class="inv-card-title">{{ $customerName }}</p>
            <div class="inv-card-body">
                @if (! empty($customer['address']))
                    <p>{{ $customer['address'] }}</p>
                @endif
                @if (! empty($customer['postcode']))
                    <p><strong>{{ $customer['postcode'] }}</strong></p>
                @endif
                <p class="inv-muted">United Kingdom</p>
            </div>
            <div class="inv-contact">
                @if (! empty($customer['email']))
                    <p>{{ $customer['email'] }}</p>
                @endif
                @if (! empty($customer['phone']))
                    <p>{{ $customer['phone'] }}</p>
                @endif
            </div>
        </div>
        <div class="inv-card">
            <div class="inv-card-head">
                <h2>Vehicle Specification</h2>
                @if (! empty($vehicle['registration']))
                    <span class="inv-plate" aria-label="Registration">
                        <span class="inv-plate-gb">GB</span>
                        {{ $vehicle['registration'] }}
                    </span>
                @endif
            </div>
            <p class="inv-card-title">{{ $vehicle['label'] ?? '—' }}</p>
            <div class="inv-vehicle-grid">
                <div>
                    <span class="inv-label">Vehicle</span>
                    <span>{{ $vehicle['label'] ?? '—' }}</span>
                </div>
                <div>
                    <span class="inv-label">Registration</span>
                    <span>{{ $vehicle['registration'] ?? '—' }}</span>
                </div>
                <div>
                    <span class="inv-label">VAT scheme</span>
                    <span class="inv-scheme">{{ $schemeLabel }}</span>
                </div>
                @if (! empty($vehicle['vat_status']))
                    <div>
                        <span class="inv-label">VAT status</span>
                        <span>{{ $vehicle['vat_status'] }}</span>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <section class="inv-lines" aria-label="Itemized charges">
        <table>
            <thead>
                <tr>
                    <th class="sno">S.no</th>
                    <th>Item &amp; Description</th>
                    <th class="num">Net</th>
                    <th class="num">VAT</th>
                    <th class="num">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoice->lines as $line)
                    <tr>
                        <td class="sno">{{ $loop->iteration }}</td>
                        <td>
                            <strong>{{ $line->description }}</strong>
                            <small>{{ ucfirst(str_replace('_', ' ', $line->line_type)) }}@if($line->quantity && (float) $line->quantity !== 1.0) · Qty {{ $line->quantity }}@endif</small>
                        </td>
                        <td class="num">@gbp($line->unit_net_pence)</td>
                        <td class="num">
                            @gbp($line->vat_pence)
                            @if ((int) $line->vat_rate_bps > 0)
                                <small>{{ number_format($line->vat_rate_bps / 100, 0) }}%</small>
                            @elseif ($invoice->vat_scheme === 'margin')
                                <small>0% margin</small>
                            @endif
                        </td>
                        <td class="num"><strong>@gbp($line->line_total_pence)</strong></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty-row">No line items.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="inv-summary-grid">
        <div class="inv-summary-left">
            <div class="inv-notice {{ $invoice->vat_scheme === 'margin' ? 'is-margin' : '' }}">
                @if ($invoice->vat_scheme === 'margin')
                    <p class="inv-notice-title">VAT Margin Scheme Disclosure</p>
                    <p><strong>Customer note:</strong> Second-hand vehicles — VAT margin scheme applied. No VAT is reclaimable on this vehicle pursuant to Section 50A of the VAT Act 1994. The buyer cannot claim any input tax on this purchase.</p>
                @elseif ($invoice->vat_scheme === 'standard')
                    <p class="inv-notice-title">VAT Notice</p>
                    <p>Standard VAT charged at {{ number_format($invoice->vat_rate_bps / 100, 2) }}% where shown on line items.</p>
                @elseif ($invoice->vat_scheme === 'exempt')
                    <p class="inv-notice-title">VAT Notice</p>
                    <p>This supply is treated as VAT exempt.</p>
                @else
                    <p class="inv-notice-title">VAT Notice</p>
                    <p>This supply is zero-rated for VAT.</p>
                @endif
            </div>

            <div class="inv-payments">
                <p class="inv-notice-title">Payment Details &amp; Settlement Log</p>
                @forelse ($invoice->payments as $payment)
                    <div class="inv-pay-row">
                        <span>{{ optional($payment->received_on)->format('j M Y') }} · {{ ucfirst(str_replace('_', ' ', $payment->method)) }}</span>
                        <strong>@gbp($payment->amount_pence)</strong>
                    </div>
                    @if ($payment->reference)
                        <div class="inv-pay-ref">Ref: {{ $payment->reference }}</div>
                    @endif
                @empty
                    <p class="inv-muted">No payments recorded yet.</p>
                @endforelse
            </div>
        </div>

        <div class="inv-totals">
            <h3>Financial Summary</h3>
            <div class="inv-total-row"><span>Net Amount</span><strong>@gbp($invoice->net_pence)</strong></div>
            <div class="inv-total-row"><span>VAT ({{ $schemeLabel }})</span><strong>@gbp($invoice->vat_pence)</strong></div>
            <div class="inv-total-row inv-total-strong"><span>Total Invoice Amount</span><strong>@gbp($invoice->total_pence)</strong></div>
            @if ($paidPence > 0)
                <div class="inv-received">
                    <div class="inv-total-row"><span>Payment Received</span><strong>- @gbp($paidPence)</strong></div>
                </div>
            @endif
            <div class="inv-balance">
                <span>Balance Due</span>
                <strong class="{{ $balancePence <= 0 ? 'is-clear' : 'is-due' }}">@gbp(max(0, $balancePence))</strong>
            </div>
        </div>
    </section>

    <section class="inv-signs" aria-label="Signatures">
        <div class="inv-sign-block">
            <p class="inv-sign-label">For {{ $dealerName }}</p>
            <div class="inv-sign-line">
                <span class="inv-sign-name">{{ $authorisedName }}</span>
            </div>
            <div class="inv-sign-meta">
                <span>
                    <strong>Authorized Signatory</strong><br>
                    {{ $authorisedRole }}
                </span>
                <span>Date: {{ $signDate ?: '—' }}</span>
            </div>
        </div>
        <div class="inv-sign-block">
            <p class="inv-sign-label">Customer Handover Acceptance</p>
            <div class="inv-sign-line">
                <span class="inv-sign-name">{{ $customerName }}</span>
            </div>
            <div class="inv-sign-meta">
                <span>
                    <strong>Customer acceptance</strong><br>
                    {{ $customerName }}
                    @if (! empty($customer['postcode']))
                        · {{ $customer['postcode'] }}
                    @endif
                </span>
                <span>Date: {{ $signDate ?: '—' }}</span>
            </div>
        </div>
    </section>

    <div class="inv-footer">
        <p><strong>Thank you for your business with {{ $dealerName }}.</strong></p>
        <p>Registered showroom: {{ $dealer->showroomAddress() }}</p>
        <p>This document serves as your official sales receipt and proof of purchase.</p>
    </div>
</article>
