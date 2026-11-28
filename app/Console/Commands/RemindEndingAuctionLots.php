<?php

namespace App\Console\Commands;

use App\Services\Auctions\AuctionNotifier;
use Illuminate\Console\Command;

class RemindEndingAuctionLots extends Command
{
    protected $signature = 'auctions:remind-ending';

    protected $description = 'Text the watchers of auction lots that close within 15 minutes (once per lot)';

    public function handle(AuctionNotifier $notifier): void
    {
        $this->info('Reminded '.$notifier->endingSoon().' watcher(s).');
    }
}
