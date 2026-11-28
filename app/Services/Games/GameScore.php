<?php

namespace App\Services\Games;

/**
 * Points for a race. The finishing place earns PLACE_POINTS; on a real race the horse's rating rank in
 * its field multiplies them, from ×1 for the best-rated horse to ×(1 + UNDERDOG_BONUS) for the
 * lowest-rated, so winning on an outsider beats winning on the favourite. Races on the game's own
 * course (no ratings) score the place points only.
 */
final class GameScore
{
    public const PLACE_POINTS = [1 => 100, 2 => 70, 3 => 50, 4 => 35, 5 => 25, 6 => 15, 7 => 10, 8 => 6, 9 => 3, 10 => 1];

    public const UNDERDOG_BONUS = 0.5;

    /** e.g. 1.25 for the middle of the field: the multiplier a horse earns, before the race. */
    public static function multiplier(?int $ratingRank, int $fieldSize): float
    {
        if ($ratingRank === null || $fieldSize < 2) {
            return 1.0;
        }

        return round(1 + self::UNDERDOG_BONUS * (min($ratingRank, $fieldSize) - 1) / ($fieldSize - 1), 2);
    }

    public static function points(int $position, ?int $ratingRank, int $fieldSize): int
    {
        return (int) round((self::PLACE_POINTS[$position] ?? 0) * self::multiplier($ratingRank, $fieldSize));
    }
}
