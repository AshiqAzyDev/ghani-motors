@if ($dealerStaff)
<div class="notify-wrap" data-notify-root data-notify-url="{{ route('dealer.notifications.unread') }}">
    <button class="notify-bell" type="button" data-notify-toggle aria-label="Notifications" aria-expanded="false">
        <span aria-hidden="true">🔔</span>
        <span class="notify-count" data-notify-count hidden>0</span>
    </button>
    <div class="notify-panel" data-notify-panel hidden>
        <div class="notify-panel-head">
            <strong>Notifications</strong>
            <form method="post" action="{{ route('dealer.notifications.read-all') }}">@csrf<button class="rowbtn" type="submit">Mark all read</button></form>
        </div>
        <div data-notify-list class="notify-list"><p class="meta">Loading…</p></div>
        <a class="notify-foot" href="{{ route('dealer.notifications') }}">View all</a>
    </div>
</div>
@endif
