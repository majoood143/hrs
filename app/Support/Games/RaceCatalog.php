<?php

namespace App\Support\Games;

/**
 * The horse racing game's race card (`src/game/races.ts` in the game's repo, built into
 * `public/games/horse-racing`): keep both in step. A campaign runs one of these races, so every
 * attempt in it is timed over the same distance against the same field.
 */
final class RaceCatalog
{
    /** The game's own path (a static build) and the page that opens it for a campaign. */
    public const GAME_PATH = '/games/horse-racing/index.html';

    public const RACES = [
        'rahba-sprint' => ['distance' => 1000, 'field' => 11, 'en' => 'Al Rahba Sprint', 'ar' => 'سباق الرحبة السريع'],
        'batinah-turf' => ['distance' => 1400, 'field' => 13, 'en' => 'Al Batinah Turf Stakes', 'ar' => 'جائزة الباطنة للعشب'],
        'muscat-mile' => ['distance' => 1600, 'field' => 14, 'en' => 'Muscat Mile', 'ar' => 'ميل مسقط'],
        'sultans-cup' => ['distance' => 2000, 'field' => 16, 'en' => "Sultan's Cup", 'ar' => 'كأس السلطان'],
        'barka-twilight' => ['distance' => 1200, 'field' => 12, 'en' => 'Barka Twilight Cup', 'ar' => 'كأس الغروب في بركاء'],
        'felaij-cup' => ['distance' => 1800, 'field' => 14, 'en' => 'Al Felaij Palm Cup', 'ar' => 'كأس نخيل الفليج'],
    ];

    /** Daily limits count Muscat's day, not the server's (UTC). */
    public const TIMEZONE = 'Asia/Muscat';

    public const DIFFICULTIES = ['easy', 'normal', 'expert'];

    public const HORSE_STYLES = ['frontRunner', 'paceSetter', 'finisher'];

    /**
     * The fastest average a player's horse can hold over a race, in metres per second. The game's
     * simulator never got a player past about 15.2 m/s on any race (every style, the best riding
     * lines, the easiest field); a time that needs more than this was not ridden.
     */
    public const MAX_AVERAGE_SPEED = 16.5;

    /** The countdown in the stalls (the game's COUNTDOWN_SECONDS) runs before the race clock starts. */
    public const COUNTDOWN_MS = 3000;

    /** @return array<string, string> race id => its name in the current language */
    public static function options(): array
    {
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

        return array_map(fn (array $race) => $race[$locale].' · '.$race['distance'].' m', self::RACES);
    }

    /** @return array{distance: int, field: int, en: string, ar: string} */
    public static function race(string $id): array
    {
        return self::RACES[$id] ?? self::RACES['rahba-sprint'];
    }

    /** The quickest time, in ms, anyone could really ride a race in. */
    public static function fastestPossibleMs(int $distance): int
    {
        return (int) floor($distance / self::MAX_AVERAGE_SPEED * 1000);
    }
}
