<?php

namespace App\Support\MobileApp;

/**
 * Where the website's checkout sends the browser back to the app once an order is settled: the app
 * opens `/pay/{order}?app_return=muhra://checkout` in an in-app browser session, which closes when
 * the browser reaches that address. Only the app's own schemes are accepted (never http(s), so the
 * parameter cannot turn the checkout into an open redirect); Expo Go's `exp://` outside production.
 */
final class AppReturnUrl
{
    public const SCHEMES = ['muhra'];

    /** Expo Go and development builds. */
    public const DEV_SCHEMES = ['exp', 'exps', 'exp+muhra'];

    public static function valid(mixed $url): ?string
    {
        if (! is_string($url) || strlen($url) > 300 || preg_match('/[\s<>"\']/', $url)) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $allowed = app()->isProduction() ? self::SCHEMES : [...self::SCHEMES, ...self::DEV_SCHEMES];

        return in_array($scheme, $allowed, true) ? $url : null;
    }

    /** The return address with the order number (and how it ended) added. */
    public static function with(string $url, string $orderNumber, string $result): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query(['order' => $orderNumber, 'result' => $result]);
    }
}
