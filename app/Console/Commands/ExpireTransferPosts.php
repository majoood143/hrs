<?php

namespace App\Console\Commands;

use App\Models\TransferPost;
use Illuminate\Console\Command;

class ExpireTransferPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transfer-posts:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark transfer posts whose transfer date has passed as expired';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $count = TransferPost::where('status', 'active')
            ->where('transfer_date', '<', now()->toDateString())
            ->update(['status' => 'expired']);

        $this->info("Expired {$count} transfer post(s).");
    }
}
