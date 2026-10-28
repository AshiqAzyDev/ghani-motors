<div class="account-metrics">
    <a class="account-metric" href="{{ route('account', 'saved') }}">
        <span>Saved cars</span>
        <strong>{{ $counts['saved'] }}</strong>
        <small>Open full list</small>
    </a>
    <a class="account-metric" href="{{ route('account', 'bookings') }}">
        <span>Bookings</span>
        <strong>{{ $counts['bookings'] }}</strong>
        <small>{{ $counts['open_bookings'] }} open</small>
    </a>
    <a class="account-metric" href="{{ route('account', 'enquiries') }}">
        <span>Enquiries</span>
        <strong>{{ $counts['enquiries'] }}</strong>
        <small>View all messages</small>
    </a>
    <a class="account-metric" href="{{ route('account', 'invoices') }}">
        <span>Invoices</span>
        <strong>{{ $counts['invoices'] }}</strong>
        <small>{{ $counts['sales'] }} purchase{{ $counts['sales'] === 1 ? '' : 's' }}</small>
    </a>
</div>

<div class="account-split">
    <section class="account-card">
        <div class="account-card-head">
            <div>
                <h2>Recent bookings</h2>
                <p class="meta">Latest reservations and appointments.</p>
            </div>
            <a class="textlink" href="{{ route('account', 'bookings') }}">View all ↗</a>
        </div>
        @forelse ($recentBookings as $booking)
            <div class="account-item">
                <div>
                    <b>{{ ucfirst(str_replace('_', ' ', $booking->type)) }}</b>
                    <p class="meta">{{ $booking->vehicle?->title() ?: 'Car' }}</p>
                    <small>{{ $booking->preferred_on?->format('j M Y') }}</small>
                </div>
                <span class="badge {{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
            </div>
        @empty
            <div class="account-empty compact"><p>No bookings yet.</p><a class="textlink" href="{{ route('stock') }}">Browse stock ↗</a></div>
        @endforelse
    </section>

    <section class="account-card">
        <div class="account-card-head">
            <div>
                <h2>Recent alerts</h2>
                <p class="meta">Updates from the showroom.</p>
            </div>
            <a class="textlink" href="{{ route('account', 'alerts') }}">View all ↗</a>
        </div>
        @forelse ($notifications as $note)
            <div class="account-alert @if(!$note->read_at) is-unread @endif">
                <div>
                    <b>{{ $note->title }}</b>
                    @if ($note->body)<p class="meta">{{ $note->body }}</p>@endif
                </div>
                <a class="btn smallbtn ghost" href="{{ route('account.notifications.read', $note) }}">Open</a>
            </div>
        @empty
            <div class="account-empty compact"><p>No alerts yet.</p></div>
        @endforelse
    </section>
</div>

<section class="account-card">
    <div class="account-card-head">
        <div>
            <h2>Saved cars</h2>
            <p class="meta">A few of your favourites.</p>
        </div>
        <a class="textlink" href="{{ route('account', 'saved') }}">View all {{ $counts['saved'] }} ↗</a>
    </div>
    @if ($recentSaved->isEmpty())
        <div class="account-empty">
            <p>You haven’t saved a car yet.</p>
            <a class="btn" href="{{ route('stock') }}">Find cars in stock</a>
        </div>
    @else
        <div class="account-saved-grid">
            @foreach ($recentSaved as $car)
                @include('site.account.partials.saved-card', ['car' => $car])
            @endforeach
        </div>
    @endif
</section>
