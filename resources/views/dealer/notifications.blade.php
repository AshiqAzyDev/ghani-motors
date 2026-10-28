@extends('layouts.dealer')
@section('title', 'Notifications')
@section('actions')
    <form method="post" action="{{ route('dealer.notifications.read-all') }}">@csrf<button class="btn smallbtn" type="submit">Mark all read</button></form>
@endsection
@section('content')
<div class="panel">
    @forelse ($items as $item)
        <div class="notify-row @if(!$item->read_at) is-unread @endif">
            <div>
                <b>{{ $item->title }}</b>
                @if ($item->body)<p class="meta">{{ $item->body }}</p>@endif
                <small>{{ $item->created_at?->timezone(config('app.timezone'))->format('j M Y H:i') }}</small>
            </div>
            <form method="post" action="{{ route('dealer.notifications.read', $item) }}">@csrf<button class="rowbtn" type="submit">{{ $item->url ? 'Open' : 'Mark read' }}</button></form>
        </div>
    @empty
        <p class="empty-row">No notifications yet.</p>
    @endforelse
</div>
@endsection
