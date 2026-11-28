<?php

namespace App\Services\Games;

use App\Support\Games\RaceCatalog;

/**
 * How believable a finished race is. The game runs on the player's phone, so its result is a claim;
 * what the server can check: the race clock cannot run faster than the server's own clock between
 * the start and the finish calls (plus the countdown in the stalls), no horse can average more than
 * RaceCatalog::MAX_AVERAGE_SPEED or reach more than MAX_TOP_SPEED_KMH, and a scripted browser says
 * so. Each sign adds points; an attempt at or above the campaign's threshold waits for an admin
 * before it can rank. Nothing is refused here, so a false alarm costs a review, never a real race.
 */
class RiskScorer
{
    /** Above the game's fastest whip speed (19.4 m/s × the best ability and style ≈ 71 km/h). */
    public const MAX_TOP_SPEED_KMH = 75;

    /** Allowance for the clocks (the server's timestamps, the game's frame steps). */
    public const CLOCK_SLACK_MS = 500;

    public const WEIGHTS = [
        'webdriver' => 60,
        'faster_than_clock' => 100,
        'impossible_time' => 100,
        'impossible_speed' => 100,
    ];

    /**
     * @param  int  $distance  the race's, in metres
     * @param  array{race_time_ms: int, top_speed_kmh: float, webdriver?: bool}  $result  what the game reported
     * @param  int  $wallMs  the server's time from the start call to the finish call
     * @return list<string>
     */
    public function flags(int $distance, array $result, int $wallMs): array
    {
        $flags = [];

        if (! empty($result['webdriver'])) {
            $flags[] = 'webdriver';
        }

        if ($wallMs + self::CLOCK_SLACK_MS < $result['race_time_ms'] + RaceCatalog::COUNTDOWN_MS) {
            $flags[] = 'faster_than_clock';
        }

        if ($result['race_time_ms'] < RaceCatalog::fastestPossibleMs($distance)) {
            $flags[] = 'impossible_time';
        }

        if ($result['top_speed_kmh'] > self::MAX_TOP_SPEED_KMH) {
            $flags[] = 'impossible_speed';
        }

        return $flags;
    }

    /** @param  list<string>  $flags */
    public function score(array $flags): int
    {
        return min(100, array_sum(array_map(fn (string $flag) => self::WEIGHTS[$flag] ?? 0, $flags)));
    }
}
