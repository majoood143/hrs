<?php

namespace App\Console\Commands;

use App\Services\Auctions\CloseLot;
use Illuminate\Console\Command;

class CloseDueAuctionLots extends Command
{
    protected $signature = 'auctions:close-due';

    protected $description = 'Close auction lots whose time is up (sold or unsold)';

    public function handle(CloseLot $close): void
    {
        $this->info('Closed '.$close->due().' lot(s).');
    }
}
