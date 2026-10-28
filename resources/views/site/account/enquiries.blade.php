<section class="account-card">
    <div class="account-card-head">
        <div>
            <h2>All enquiries</h2>
            <p class="meta">Page {{ $leads->currentPage() }} of {{ max($leads->lastPage(), 1) }}</p>
        </div>
    </div>
    @forelse ($leads as $lead)
        <div class="account-item">
            <div>
                <b>{{ $lead->subject }}</b>
                <p class="meta">
                    @if ($lead->vehicle?->slug)
                        <a href="{{ route('cars.show', $lead->vehicle->slug) }}">{{ $lead->vehicle->title() }}</a> ·
                    @endif
                    {{ ucfirst(str_replace('_', ' ', $lead->type)) }}
                </p>
                <small>{{ $lead->created_on?->format('j M Y') ?? $lead->created_at?->format('j M Y') }}</small>
            </div>
            <span class="badge {{ $lead->status }}">{{ ucfirst($lead->status) }}</span>
        </div>
    @empty
        <div class="account-empty">
            <p>No enquiries yet.</p>
            <a class="btn" href="{{ route('stock') }}">Ask about a car</a>
        </div>
    @endforelse
    <div class="account-pager">{{ $leads->links() }}</div>
</section>
