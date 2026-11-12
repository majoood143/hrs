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
    protected $description = 'Mark horse sale posts live for more than 60 days as expired';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $cutoff = now()->subDays(60);

        // from when it last went live (approved or switched back on), else when it was posted
        $count = HorseSalePost::where('status', 'active')
            ->where(fn ($query) => $query->where('live_since', '<', $cutoff)
                ->orWhere(fn ($query) => $query->whereNull('live_since')->where('created_at', '<', $cutoff)))
            ->update(['status' => 'expired']);

        $this->info("Expired {$count} horse sale post(s).");
    }
}
