<?php

namespace App\Support;

class Localized
{
    /**
     * Resolve a per-language value out of a `['en' => ..., 'ar' => ...]`
     * shaped array (as produced by the bilingual CMS block fields), falling
     * back to the configured default language, then to any first value.
     */
    public static function value(?array $data, string $key, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $default = config('languages.default', 'en');

        $value = data_get($data, "{$key}.{$locale}");

        if (filled($value)) {
            return $value;
        }

        $value = data_get($data, "{$key}.{$default}");

        if (filled($value)) {
            return $value;
        }

        $fallback = data_get($data, $key);

        return is_array($fallback) ? (reset($fallback) ?: null) : $fallback;
    }
}
