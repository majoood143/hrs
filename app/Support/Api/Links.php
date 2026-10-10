<?php

namespace App\Support\Api;

class Links
{
    /**
     * An admin-entered link as the app may open it: site-relative ("/stables") made absolute, and
     * only web, mail and phone links (never `javascript:` and the like).
     */
    public static function absolute(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (preg_match('#^([a-z][a-z0-9+.-]*):#i', $url, $scheme)) {
            return in_array(strtolower($scheme[1]), ['http', 'https', 'mailto', 'tel'], true) ? $url : null;
        }

        return str_starts_with($url, '//') ? 'https:'.$url : url($url);
    }
}
