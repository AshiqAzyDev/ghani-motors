<?php

namespace App\Services\Autotrader;

use App\Models\Dealer;
use App\Models\SyncRun;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class StockRefresh
{
    /**
     * Start a baseline in the background when the saved stock is old.
     * Autotrader limits stock calls until a public webhook is configured,
     * so a normal page view never starts more than one refresh per interval.
     */
    public function kick(Dealer $dealer): void
    {
        if (! $dealer->advertiser_id || ! $this->due($dealer)) {
            return;
        }

        $lock = 'autotrader-baseline-'.$dealer->id;
        if (! Cache::add($lock, true, now()->addMinutes(20))) {
            return;
        }

        $this->start((int) $dealer->id);
    }

    private function due(Dealer $dealer): bool
    {
        $hours = max(1, (int) config('autotrader.baseline_refresh_hours', 12));
        $latest = SyncRun::query()
            ->where('dealer_id', $dealer->id)
            ->where('kind', 'baseline')
            ->latest('id')
            ->first();

        if (! $latest) {
            return true;
        }

        if ($latest->status === 'running' && $latest->started_at && $latest->started_at->gt(now()->subMinutes(20))) {
            return false;
        }

        $stamp = $latest->finished_at ?? $latest->started_at;

        return ! $stamp || $stamp->lte(now()->subHours($hours));
    }

    private function start(int $dealerId): void
    {
        $php = PHP_BINARY;
        $artisan = base_path('artisan');

        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen('start /B "" '.escapeshellarg($php).' '.escapeshellarg($artisan).' autotrader:baseline --dealer='.$dealerId, 'r'));

            return;
        }

        $process = new Process([$php, $artisan, 'autotrader:baseline', '--dealer='.$dealerId]);
        $process->setWorkingDirectory(base_path());
        $process->disableOutput();
        $process->start();
    }
}
