<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\Autotrader\StockSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ApplyStockNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $eventId) {}

    public function handle(StockSync $sync): void
    {
        $event = WebhookEvent::query()->find($this->eventId);
        if ($event) {
            $sync->applyNotification($event);
        }
    }
}
