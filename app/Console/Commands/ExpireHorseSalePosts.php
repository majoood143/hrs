<?php

namespace App\Console\Commands;

use App\Models\HorseSalePost;
use Illuminate\Console\Command;

class ExpireHorseSalePosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'horse-sale-posts:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark horse sale posts older than 60 days as expired';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $count = HorseSalePost::where('status', 'active')
            ->where('created_at', '<', now()->subDays(60))
            ->update(['status' => 'expired']);

        $this->info("Expired {$count} horse sale post(s).");
    }
}
