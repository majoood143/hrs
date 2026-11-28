<?php

namespace App\Console\Commands;

use App\Services\Auctions\Settlement;
use Illuminate\Console\Command;

class SweepAuctionInvoices extends Command
{
    protected $signature = 'auctions:invoices';

    protected $description = 'Remind auction winners a day before their invoice is due, and flag the overdue ones';

    public function handle(Settlement $settlement): void
    {
        $done = $settlement->sweep();
        $this->info("Reminded {$done['reminded']}, flagged {$done['overdue']} overdue.");
    }
}
