<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Services\AppNotifier;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private AppNotifier $notifier) {}

    public function dealerIndex(Request $request)
    {
        $staff = $request->user('staff');
        $items = $this->notifier->recentFor('staff', $staff->id, 30);

        return view('dealer.notifications', compact('items'));
    }

    public function dealerUnread(Request $request)
    {
        $staff = $request->user('staff');

        return response()->json([
            'count' => $this->notifier->unreadCount('staff', $staff->id),
            'items' => $this->notifier->unreadFor('staff', $staff->id)->map(fn (AppNotification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'url' => $n->url,
                'created_at' => $n->created_at?->diffForHumans(),
            ])->values(),
        ]);
    }

    public function dealerMarkRead(Request $request, AppNotification $notification)
    {
        $staff = $request->user('staff');
        abort_unless($notification->notifiable_type === 'staff' && (int) $notification->notifiable_id === (int) $staff->id, 404);
        $notification->markRead();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect($notification->url ?: route('dealer.home'));
    }

    public function dealerMarkAll(Request $request)
    {
        $this->notifier->markAllRead('staff', $request->user('staff')->id);

        return back()->with('status', 'Notifications marked as read.');
    }

    public function customerMarkRead(Request $request, AppNotification $notification)
    {
        $customer = $request->user();
        abort_unless($notification->notifiable_type === 'customer' && (int) $notification->notifiable_id === (int) $customer->id, 404);
        $notification->markRead();

        return redirect($notification->url ?: route('account'));
    }

    public function customerMarkAll(Request $request)
    {
        $this->notifier->markAllRead('customer', $request->user()->id);

        return redirect()->route('account', 'alerts')->with('status', 'Notifications marked as read.');
    }
}
