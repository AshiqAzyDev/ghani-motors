<?php

namespace App\Console\Commands;

use App\Models\Dealer;
use App\Models\Lead;
use App\Services\Mail\GhaniMailer;
use Illuminate\Console\Command;

class SendLeadDigest extends Command
{
    protected $signature = 'mail:lead-digest';

    protected $description = 'Email dealer staff a digest of open leads';

    public function handle(GhaniMailer $mailer): int
    {
        $dealers = Dealer::query()->orderBy('id')->get();
        $total = 0;

        foreach ($dealers as $dealer) {
            $leads = Lead::query()
                ->with(['customer', 'vehicle'])
                ->whereHas('customer', fn ($q) => $q->where('dealer_id', $dealer->id))
                ->where('status', 'new')
                ->latest('id')
                ->limit(50)
                ->get();

            if ($leads->isEmpty()) {
                continue;
            }

            $mailer->leadDigest($dealer, $leads);
            $total += $leads->count();
            $this->info("Dealer {$dealer->id}: {$leads->count()} open lead(s).");
        }

        $this->info("Digest covered {$total} open lead".($total === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
