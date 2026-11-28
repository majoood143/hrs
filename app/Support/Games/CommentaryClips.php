<?php

namespace App\Support\Games;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;

/**
 * Recorded commentary for the horse racing game (admin → Games → Commentator sounds): one clip per
 * moment of the race, English and Arabic, said without horse names (the captions keep them). The
 * game reads them from `GET /game-sounds`; a moment without a clip is spoken by the phone's voice.
 * MOMENTS = the keys of `lines` in the game's `src/audio/Commentator.ts`: keep both in step.
 */
class CommentaryClips
{
    public const MOMENTS = ['intro', 'off', 'leader', 'early', 'halfway', 'fourHundred', 'furlong', 'boxedIn', 'winner', 'playerWins', 'playerPlaced'];

    public const LANGUAGES = ['en', 'ar'];

    public const SETTING = 'games.commentary_clips';

    public const DIRECTORY = 'games/commentary';

    /** @return array<string, array<string, string>> moment => language => stored path */
    public static function paths(): array
    {
        $saved = json_decode((string) SiteSetting::get(self::SETTING, ''), true);

        return is_array($saved) ? $saved : [];
    }

    /** @param  array<string, array<string, string|null>>  $paths */
    public static function save(array $paths): void
    {
        $clean = [];

        foreach (self::MOMENTS as $moment) {
            foreach (self::LANGUAGES as $language) {
                if (is_string($path = $paths[$moment][$language] ?? null) && $path !== '') {
                    $clean[$moment][$language] = $path;
                }
            }
        }

        SiteSetting::set(self::SETTING, json_encode($clean), 'text', null, 'games');
    }

    /** @return array<string, array<string, string>> moment => language => absolute URL (only the uploaded ones) */
    public static function urls(): array
    {
        return array_map(
            fn (array $languages) => array_map(fn (string $path) => Storage::disk('public')->url($path), $languages),
            array_intersect_key(self::paths(), array_flip(self::MOMENTS)),
        );
    }
}
