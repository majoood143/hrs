<?php

namespace App\Console\Commands;

use App\Services\Racing\RacingClient;
use App\Services\Racing\RacingUnavailableException;
use Illuminate\Console\Command;

class WarmRacingCache extends Command
{
    protected $signature = 'racing:warm';

    protected $description = 'Refresh the racing lists (handicap ratings, and the statistics behind the Arabic name search) so visitors never wait for a cold cache';

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

            // the lists behind the Arabic name search
            foreach (['owner', 'jockey', 'trainer'] as $entity) {
                try {
                    $count = $racing->refreshPeople($entity, $locale);

                    $this->info("Statistics, {$entity}s ({$locale}): {$count} cached.");
                } catch (RacingUnavailableException $e) {
                    $this->error("Statistics, {$entity}s ({$locale}): {$e->getMessage()}");
                    $failed = true;
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
