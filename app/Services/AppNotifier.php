<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\StaffUser;
use Illuminate\Support\Collection;

class AppNotifier
{
    public function notifyStaff(Dealer $dealer, string $type, string $title, ?string $body = null, ?string $url = null, array $roles = ['admin', 'sales']): void
    {
        $staff = StaffUser::query()
            ->where('dealer_id', $dealer->id)
            ->where('is_active', true)
            ->whereIn('role', $roles)
            ->get();

        foreach ($staff as $user) {
            if ($type === 'sync.failure' && ! $user->can('sync.run') && ! $user->isAdmin()) {
                continue;
            }
            $this->store('staff', $user->id, $dealer->id, $type, $title, $body, $url);
        }
    }

    public function notifyCustomer(Customer $customer, string $type, string $title, ?string $body = null, ?string $url = null): void
    {
        $this->store('customer', $customer->id, $customer->dealer_id, $type, $title, $body, $url);
    }

    public function unreadFor(string $type, int $id): Collection
    {
        return AppNotification::query()
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->whereNull('read_at')
            ->latest('id')
            ->limit(20)
            ->get();
    }

    public function recentFor(string $type, int $id, int $limit = 20): Collection
    {
        return AppNotification::query()
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function unreadCount(string $type, int $id): int
    {
        return AppNotification::query()
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->whereNull('read_at')
            ->count();
    }

    public function markAllRead(string $type, int $id): void
    {
        AppNotification::query()
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    private function store(string $type, int $id, ?int $dealerId, string $event, string $title, ?string $body, ?string $url): void
    {
        AppNotification::query()->create([
            'notifiable_type' => $type,
            'notifiable_id' => $id,
            'dealer_id' => $dealerId,
            'type' => $event,
            'title' => mb_substr($title, 0, 180),
            'body' => $body ? mb_substr($body, 0, 500) : null,
            'url' => $url,
        ]);
    }
}
