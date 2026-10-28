<section class="account-card">
    <div class="account-card-head">
        <div>
            <h2>All alerts</h2>
            <p class="meta">{{ $counts['alerts'] }} unread · page {{ $notifications->currentPage() }} of {{ max($notifications->lastPage(), 1) }}</p>
        </div>
        @if ($counts['alerts'] > 0)
            <form method="post" action="{{ route('account.notifications.read-all') }}">@csrf<button class="btn smallbtn ghost" type="submit">Mark all read</button></form>
        @endif
    </div>
    @forelse ($notifications as $note)
        <div class="account-alert @if(!$note->read_at) is-unread @endif">
            <div>
                <b>{{ $note->title }}</b>
                @if ($note->body)<p class="meta">{{ $note->body }}</p>@endif
                <small>{{ $note->created_at?->diffForHumans() }}</small>
            </div>
            <a class="btn smallbtn ghost" href="{{ route('account.notifications.read', $note) }}">Open</a>
        </div>
    @empty
        <div class="account-empty"><p>No alerts yet. Booking and invoice updates will show here.</p></div>
    @endforelse
    <div class="account-pager">{{ $notifications->links() }}</div>
</section>
