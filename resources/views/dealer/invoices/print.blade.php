<!DOCTYPE html>
<html lang="en-GB" class="inv-print-html">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $invoice->number }} | {{ $dealer->displayName() }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
    {{-- upgrades only: site.css public header/nav/h1 rules break print layout --}}
    <link rel="stylesheet" href="{{ asset('css/upgrades.css') }}">
    <style>
        :root {
            --ink: #101b22;
            --brand: #00a0d0;
            --teal: #007b9f;
            --light: #eef7f8;
            --line: #e0e6e8;
            --muted: #67747d;
            /* Screen mock of printable area: A4 − @page margins */
            --inv-page-margin: 10mm;
            --inv-sheet-max: calc(210mm - (2 * var(--inv-page-margin)));
        }
        * { box-sizing: border-box; }
        html.inv-print-html {
            font-size: 16px;
        }
        html.inv-print-html,
        html.inv-print-html body {
            margin: 0;
            padding: 0;
            color: var(--ink);
            background: #f1f5f9;
            font: 13px/1.45 'DM Sans', Arial, sans-serif;
        }
        html.inv-print-html a { color: inherit; text-decoration: none; }
        html.inv-print-html button { font: inherit; cursor: pointer; border: 0; }
        html.inv-print-html h1,
        html.inv-print-html h2,
        html.inv-print-html h3,
        html.inv-print-html p { margin: 0; }
        html.inv-print-html h2,
        html.inv-print-html h3 { font-family: Manrope, Arial, sans-serif; }
        .inv-print-page {
            min-height: 100vh;
            padding: 1.25rem 1rem 2rem;
            background: #f1f5f9;
        }
        .inv-print-page .inv-toolbar,
        .inv-print-page .inv-sheet {
            width: 100%;
            max-width: var(--inv-sheet-max);
            margin-left: auto;
            margin-right: auto;
        }
        .inv-print-page .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 37px;
            padding: 9px 13px;
            border-radius: 6px;
            background: #007b9f;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
        }
        .inv-print-page .btn.ghost {
            background: #fff;
            color: #12232e;
            border: 1px solid #d0d7db;
        }
        .inv-print-page .btn.smallbtn {
            min-height: 34px;
            padding: 8px 12px;
        }

        /*
         * Size to the CSS page box (width: 100%), never fixed 210×297mm —
         * that overflows Chrome Default margins and forces manual Scale %.
         * Sheet min-height matches the printable area so content fills A4.
         */
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        @media print {
            html.inv-print-html {
                font-size: 13px !important;
            }
            html.inv-print-html,
            html.inv-print-html body,
            .inv-print-page {
                width: auto !important;
                max-width: none !important;
                height: auto !important;
                min-height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
                color: #101b22 !important;
                overflow: visible !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print,
            .inv-toolbar { display: none !important; }
            .inv-sheet {
                width: 100% !important;
                max-width: 100% !important;
                /*
                 * Printable A4 ≈ 297mm − 2×10mm = 277mm.
                 * Use a touch under that so Chrome rounding never spills to page 2,
                 * then flex pushes signatures/footer to the bottom of the sheet.
                 */
                min-height: 268mm !important;
                height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                border: 0 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                background: #fff !important;
                overflow: visible !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: space-between !important;
                gap: 0 !important;
            }
            .inv-sheet table {
                width: 100% !important;
                max-width: 100% !important;
                table-layout: fixed !important;
            }

            .inv-brand {
                padding-bottom: 1.15rem !important;
                margin-bottom: 1.15rem !important;
                gap: .85rem 1.1rem !important;
                flex: 0 0 auto;
            }
            .inv-wordmark { font-size: 1.75rem !important; }
            .inv-number { font-size: 1.95rem !important; }
            .inv-kind { font-size: .7rem !important; }
            .inv-dealer-meta { margin-top: .6rem !important; font-size: .8rem !important; line-height: 1.55 !important; }
            .inv-stamp { padding: .5rem .75rem !important; margin-top: .4rem !important; }

            .inv-parties {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: .9rem !important;
                margin-bottom: 1.15rem !important;
                flex: 0 0 auto;
            }
            .inv-vehicle-grid {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: .55rem .85rem !important;
            }
            .inv-brand-right {
                text-align: right !important;
                margin-left: auto !important;
                align-items: flex-end !important;
                flex: 0 1 15rem !important;
            }
            .inv-card { padding: .9rem 1rem !important; border-radius: 10px !important; }
            .inv-card-head { padding-bottom: .5rem !important; margin-bottom: .5rem !important; }
            .inv-card-head h2 { font-size: .7rem !important; }
            .inv-card-title { font-size: 1rem !important; margin-bottom: .35rem !important; }
            .inv-card-body,
            .inv-contact { font-size: .82rem !important; line-height: 1.55 !important; }

            .inv-lines {
                margin-bottom: 1.15rem !important;
                border-radius: 10px !important;
                flex: 0 0 auto;
            }
            .inv-lines table { font-size: .88rem !important; }
            .inv-lines th { padding: .7rem .85rem !important; font-size: .7rem !important; }
            .inv-lines td { padding: .85rem .85rem !important; }

            .inv-summary-grid {
                display: grid !important;
                grid-template-columns: 1.3fr 1fr !important;
                gap: 1rem !important;
                padding-bottom: 0 !important;
                margin-bottom: 0 !important;
                flex: 0 0 auto;
                align-content: start;
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .inv-notice,
            .inv-payments,
            .inv-totals {
                padding: .85rem .95rem !important;
                border-radius: 10px !important;
            }
            .inv-notice p { font-size: .82rem !important; line-height: 1.55 !important; }
            .inv-payments { margin-top: .75rem !important; }
            .inv-pay-row { font-size: .82rem !important; padding: .35rem 0 !important; }
            .inv-total-row { font-size: .9rem !important; margin-bottom: .5rem !important; }
            .inv-balance strong { font-size: 1.4rem !important; }
            .inv-received { padding: .55rem .65rem !important; margin: .55rem 0 !important; }

            .inv-signs {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 1.5rem !important;
                margin: 0 !important;
                padding-top: .5rem !important;
                flex: 0 0 auto;
            }
            .inv-sign-line {
                min-height: 4.5rem !important;
                padding-bottom: .35rem !important;
            }
            .inv-sign-name { font-size: 1.08rem !important; }
            .inv-sign-label { font-size: .7rem !important; margin-bottom: .55rem !important; }
            .inv-sign-meta { margin-top: .45rem !important; font-size: .74rem !important; }

            .inv-footer {
                padding: .75rem 0 0 !important;
                margin-top: 0 !important;
                font-size: .78rem !important;
                flex: 0 0 auto;
            }
            .inv-footer p { margin: 0 0 .25rem !important; }

            .inv-card,
            .inv-totals,
            .inv-notice,
            .inv-payments,
            .inv-sign-block {
                break-inside: auto;
                page-break-inside: auto;
            }
        }
    </style>
</head>
<body class="inv-print-page">
<nav class="inv-toolbar no-print" aria-label="Invoice actions">
    <div class="inv-toolbar-left">
        <a class="btn smallbtn ghost" href="{{ route('dealer.invoices.show', $invoice) }}">← Back to invoice</a>
        <span class="inv-toolbar-sep" aria-hidden="true">|</span>
        <span class="inv-status-pill {{ $invoice->status }}">
            <i aria-hidden="true"></i>
            {{ ucfirst(str_replace('_', ' ', $invoice->status)) }}
        </span>
    </div>
    <div class="inv-toolbar-right">
        <button class="btn smallbtn" type="button" onclick="window.print()">Print invoice</button>
        <button class="btn smallbtn ghost" type="button" onclick="window.print()">PDF</button>
    </div>
</nav>

@include('dealer.invoices._document', ['invoice' => $invoice, 'dealer' => $dealer])
</body>
</html>
