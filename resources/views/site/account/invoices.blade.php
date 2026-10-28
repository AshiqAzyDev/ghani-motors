<section class="account-card">
    <div class="account-card-head">
        <div>
            <h2>Invoices</h2>
            <p class="meta">Page {{ $invoices->currentPage() }} of {{ max($invoices->lastPage(), 1) }}</p>
        </div>
    </div>
    @forelse ($invoices as $invoice)
        <div class="account-item">
            <div>
                <b>{{ $invoice->number }}</b>
                <p class="meta">@gbp($invoice->total_pence) · {{ ucfirst(str_replace('_', ' ', $invoice->kind)) }}</p>
            </div>
            <span class="badge {{ $invoice->status }}">{{ ucfirst(str_replace('_', ' ', $invoice->status)) }}</span>
        </div>
    @empty
        <div class="account-empty compact"><p>No invoices yet.</p></div>
    @endforelse
    <div class="account-pager">{{ $invoices->links() }}</div>
</section>

<section class="account-card">
    <div class="account-card-head">
        <div>
            <h2>Purchases</h2>
            <p class="meta">Completed sales on your account.</p>
        </div>
    </div>
    @forelse ($sales as $sale)
        <div class="account-item">
            <div>
                <b>{{ $sale->vehicle?->title() ?: $sale->vehicle_label }}</b>
                <p class="meta">{{ $sale->sold_on?->format('j M Y') }} · @gbp($sale->price_pence)</p>
            </div>
            <span class="badge completed">Completed</span>
        </div>
    @empty
        <div class="account-empty compact"><p>No purchases yet.</p></div>
    @endforelse
    <div class="account-pager">{{ $sales->links() }}</div>
</section>
