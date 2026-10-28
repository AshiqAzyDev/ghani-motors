<section class="account-card">
    <div class="account-card-head">
        <div>
            <h2>All bookings</h2>
            <p class="meta">{{ $counts['open_bookings'] }} open · showing page {{ $bookings->currentPage() }} of {{ max($bookings->lastPage(), 1) }}</p>
        </div>
    </div>
    @forelse ($bookings as $booking)
        <div class="account-item">
            <div>
                <b>{{ ucfirst(str_replace('_', ' ', $booking->type)) }}</b>
                <p class="meta">
                    @if ($booking->vehicle?->slug)
                        <a href="{{ route('cars.show', $booking->vehicle->slug) }}">{{ $booking->vehicle->title() }}</a>
                    @else
                        {{ $booking->vehicle?->title() ?: 'Car' }}
                    @endif
                </p>
                <small>
                    {{ $booking->preferred_on?->format('j M Y') }}
                    @if ($booking->preferred_time) · {{ \Illuminate\Support\Str::of($booking->preferred_time)->substr(0, 5) }}@endif
                </small>
            </div>
            <span class="badge {{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
        </div>
    @empty
        <div class="account-empty">
            <p>No bookings yet.</p>
            <a class="btn" href="{{ route('stock') }}">Reserve or book a viewing</a>
        </div>
    @endforelse
    <div class="account-pager">{{ $bookings->links() }}</div>
</section>
