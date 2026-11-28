<?php

namespace App\Console\Commands;

use App\Models\GameAttempt;
use Illuminate\Console\Command;

/** Marks game races nobody finished as abandoned (they already counted against the player's limits). */
class ExpireGameAttempts extends Command
{
    protected $signature = 'games:expire-attempts';

    protected $description = 'Mark unfinished game races older than the time limit as abandoned';

    public function handle(): int
    {
        $count = GameAttempt::query()
            ->where('status', GameAttempt::STATUS_STARTED)
            ->where('started_at', '<', now()->subMinutes(GameAttempt::ABANDON_AFTER_MINUTES))
            ->update(['status' => GameAttempt::STATUS_ABANDONED, 'updated_at' => now()]);

        $this->info("{$count} race(s) marked as abandoned.");

        return self::SUCCESS;
    }
}
