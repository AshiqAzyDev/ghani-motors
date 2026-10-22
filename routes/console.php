<?php

use App\Exceptions\AutotraderException;
use App\Models\Dealer;
use App\Models\Integration;
use App\Services\Autotrader\StockSync;
use Illuminate\Support\Facades\Artisan;

Artisan::command('autotrader:baseline {--dealer=1}', function (StockSync $sync) {
    $dealer = Dealer::query()->findOrFail((int) $this->option('dealer'));
    try {
        $run = $sync->baseline($dealer);
        $this->info('Baseline '.$run->status.'. '.$run->vehicles_upserted.' cars.');
    } catch (AutotraderException $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    return 0;
})->purpose('Pull Autotrader stock and hide cars missing from the feed');

Artisan::command('autotrader:advertisers', function (StockSync $sync) {
    $environment = (string) config('autotrader.environment');
    $integration = Integration::query()->where('environment', $environment)->firstOrFail();
    try {
        $run = $sync->refreshAdvertisers($integration);
        $this->info('Advertisers '.$run->status.'.');
    } catch (AutotraderException $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    return 0;
})->purpose('Refresh which dealers are still on the Autotrader integration');
