<?php

namespace App\Services\WhatsApp;

use App\Support\Locale;
use Closure;

/**
 * An admin-facing WhatsApp text in every site language, one after the other (English first),
 * split by a divider: the builder runs once inside each locale, so __() and the models'
 * locale-aware accessors ($post->name, $city->name) come out in that language.
 */
final class BilingualMessage
{
    public const DIVIDER = '──────────────';

    /** @param  Closure(string $locale): string  $build */
    public static function make(Closure $build): string
    {
        $parts = [];

        foreach (array_keys(config('languages.available', ['en' => [], 'ar' => []])) as $locale) {
            $text = trim((string) Locale::within($locale, fn () => $build($locale)));

            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode("\n\n".self::DIVIDER."\n\n", $parts);
    }
}
