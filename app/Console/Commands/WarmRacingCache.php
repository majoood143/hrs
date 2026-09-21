<?php

namespace App\Console\Commands;

use App\Services\Racing\RacingClient;
use App\Services\Racing\RacingUnavailableException;
use Illuminate\Console\Command;

class WarmRacingCache extends Command
{
    protected $signature = 'racing:warm';

    protected $description = 'Refresh the slow racing lists (handicap ratings) so visitors never wait ~10s for a cold cache';

    public function handle(RacingClient $racing): int
    {
        $failed = false;

        foreach (array_keys(config('languages.available')) as $locale) {
            try {
                $count = $racing->refreshHandicap('', '1', $locale);

                $this->info("Handicap ratings ({$locale}): {$count} horses cached.");
            } catch (RacingUnavailableException $e) {
                $this->error("Handicap ratings ({$locale}): {$e->getMessage()}");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
