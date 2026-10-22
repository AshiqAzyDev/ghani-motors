<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }} | Ghani Motors</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700&family=Source+Sans+3:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    <link rel="stylesheet" href="{{ asset('css/upgrades.css') }}">
</head>
<body>
<div class="sheet">
    <header class="invoice-head">
        <div>
            <p class="wordmark" style="margin:0;">GHANI <span>MOTORS</span></p>
            <p style="margin:.2rem 0 0;">AA Approved Dealer</p>
        </div>
        <div>
            <h1 style="margin:0;">{{ $invoice->number }}</h1>
            <p style="margin:.2rem 0 0;">{{ str_replace('_', ' ', $invoice->kind) }}</p>
        </div>
    </header>
    <div style="padding:1.2rem 1.4rem 1.6rem;">
        <button class="btn no-print" onclick="window.print()">Print</button>
        <p>{{ $dealer->name }}<br>{{ $dealer->address_line }}, {{ $dealer->city }} {{ $dealer->postcode }}<br>{{ $dealer->phone }}</p>
        <p><strong>{{ $invoice->customer_snapshot['name'] ?? '' }}</strong><br>{{ $invoice->customer_snapshot['address'] ?? '' }} {{ $invoice->customer_snapshot['postcode'] ?? '' }}<br>{{ $invoice->customer_snapshot['email'] ?? '' }} · {{ $invoice->customer_snapshot['phone'] ?? '' }}</p>
        <p>{{ $invoice->vehicle_snapshot['label'] ?? '' }} {{ $invoice->vehicle_snapshot['registration'] ?? '' }}</p>
        <p>VAT {{ str_replace('_', ' ', $invoice->vat_scheme) }}@if($invoice->vat_scheme === 'standard') ({{ number_format($invoice->vat_rate_bps / 100, 2) }}%)@endif · {{ str_replace('_', ' ', $invoice->status) }}</p>
        <table>
            <tr><th>Description</th><th>Net</th><th>VAT</th><th>Total</th></tr>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td>@gbp($line->unit_net_pence)</td>
                    <td>@gbp($line->vat_pence)</td>
                    <td>@gbp($line->line_total_pence)</td>
                </tr>
            @endforeach
        </table>
        <p>Net @gbp($invoice->net_pence)</p>
        <p>VAT @gbp($invoice->vat_pence)</p>
        <p><strong>Total @gbp($invoice->total_pence)</strong></p>
        @foreach ($invoice->payments as $payment)
            <p>Payment {{ $payment->received_on?->format('j M Y') }} @gbp($payment->amount_pence) {{ $payment->reference }}</p>
        @endforeach
        <p>Balance @gbp($invoice->balancePence())</p>
    </div>
</div>
</body>
</html>
