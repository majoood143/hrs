<?php

namespace App\Support\MobileApp;

use App\Models\SiteSetting;

/**
 * App versions per platform (admin → Mobile app → Updates), stored in the `mobile.updates` site
 * setting: below `min_version` the app must update (a blocking screen), below `latest_version` it
 * may (a popup it can put off). Messages can be rewritten per language; empty = the built-in text.
 */
final class MobileUpdates
{
    public const NONE = 'none';

    public const OPTIONAL = 'optional';

    public const REQUIRED = 'required';

    /**
     * @return array{ios: array{latest_version: ?string, min_version: ?string, app_store_id: ?string}, android: array{latest_version: ?string, min_version: ?string}, messages: array<string, array<string, ?string>>}
     */
    public static function settings(): array
    {
        $stored = self::stored();
        $version = fn (string $platform, string $key) => AppVersion::valid($v = trim((string) ($stored[$platform][$key] ?? ''))) ? $v : null;
        $storeId = preg_replace('/\D/', '', (string) ($stored['ios']['app_store_id'] ?? ''));

        $messages = [];
        foreach ([self::OPTIONAL, self::REQUIRED] as $kind) {
            foreach (self::locales() as $locale) {
                $text = trim((string) ($stored['messages'][$kind][$locale] ?? ''));
                $messages[$kind][$locale] = $text !== '' ? $text : null;
            }
        }

        return [
            'ios' => [
                'latest_version' => $version('ios', 'latest_version'),
                'min_version' => $version('ios', 'min_version'),
                'app_store_id' => $storeId !== '' ? $storeId : null,
            ],
            'android' => [
                'latest_version' => $version('android', 'latest_version'),
                'min_version' => $version('android', 'min_version'),
            ],
            'messages' => $messages,
        ];
    }

    /** @param  array<string, mixed>  $state  the admin form's `updates` state */
    public static function save(array $state): void
    {
        SiteSetting::set('mobile.updates', json_encode($state, JSON_UNESCAPED_UNICODE), 'text', null, 'mobile_app');
    }

    /**
     * What this app is told: none / optional / required, the versions, the store links and the
     * message in the current language.
     *
     * @return array{status: string, latest_version: ?string, min_version: ?string, store_url: ?string, store_app_url: ?string, message: ?string}
     */
    public static function forClient(AppClient $client): array
    {
        $settings = self::settings();
        $platform = $client->platform !== null ? $settings[$client->platform] : null;
        $latest = $platform['latest_version'] ?? null;
        $min = $platform['min_version'] ?? null;

        $status = match (true) {
            $client->version === null || $platform === null => self::NONE,
            $min !== null && AppVersion::lessThan($client->version, $min) => self::REQUIRED,
            $latest !== null && AppVersion::lessThan($client->version, $latest) => self::OPTIONAL,
            default => self::NONE,
        };

        [$storeUrl, $storeAppUrl] = $client->platform !== null ? self::storeUrls($client->platform, $settings) : [null, null];

        return [
            'status' => $status,
            'latest_version' => $latest,
            'min_version' => $min,
            'store_url' => $storeUrl,
            'store_app_url' => $storeAppUrl,
            'message' => $status === self::NONE ? null : self::message($status, $settings),
        ];
    }

    /**
     * The store page on the web, and the link that opens the store app directly (null when there is
     * none: iOS without the App Store id falls back to a search on the web).
     *
     * @return array{string, ?string}
     */
    public static function storeUrls(string $platform, ?array $settings = null): array
    {
        $settings ??= self::settings();

        if ($platform === 'android') {
            $package = (string) config('mobile_app.android_package');

            return ['https://play.google.com/store/apps/details?id='.rawurlencode($package), 'market://details?id='.rawurlencode($package)];
        }

        if ($id = $settings['ios']['app_store_id']) {
            return ['https://apps.apple.com/app/id'.$id, 'itms-apps://apps.apple.com/app/id'.$id];
        }

        return ['https://apps.apple.com/search?term='.rawurlencode((string) config('mobile_app.store_search_term')), null];
    }

    private static function message(string $status, array $settings): string
    {
        return $settings['messages'][$status][app()->getLocale()] ?? __('api.app.update_'.$status);
    }

    /** @return list<string> */
    public static function locales(): array
    {
        return array_keys(config('languages.available', ['en' => []]));
    }

    /** @return array<string, mixed> */
    private static function stored(): array
    {
        $value = SiteSetting::get('mobile.updates');
        $decoded = is_string($value) && $value !== '' ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
