<?php

namespace App\Console\Commands;

use App\Services\Racing\RaceResultAlerts;
use App\Services\Racing\RacingUnavailableException;
use Illuminate\Console\Command;

/**
 * Today's race results to the admins by WhatsApp, each race once (App\Services\Racing\RaceResultAlerts).
 * Scheduled every 10 minutes; does nothing on days without races or with the alert switched off.
 */
class SendRaceResultAlerts extends Command
{
    protected $signature = 'racing:result-alerts';

    protected $description = "Send today's newly published race results to the admins by WhatsApp";

    public function handle(RaceResultAlerts $alerts): int
    {
        try {
            $done = $alerts->checkToday();
        } catch (RacingUnavailableException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sent {$done['sent']}, waiting for the replay {$done['waiting']}, replay follow-ups {$done['videos']}.");

        return self::SUCCESS;
    }
}
