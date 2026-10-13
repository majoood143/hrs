<?php

namespace App\Support\MobileApp;

use App\Models\SiteSetting;

/**
 * Website links that open the Muhra app (iOS universal links, Android App Links): the two files
 * the phones fetch from `/.well-known/` (`Site\AppLinksController`) and their keys (admin →
 * Mobile app → Website links, the `mobile.links` site setting). Without a key a file answers 404,
 * so links keep opening the website.
 *
 * SECTIONS must match the app: `src/lib/website-links.ts` and app.json (`ios.associatedDomains`,
 * `android.intentFilters`). A section the app cannot show must not be claimed.
 */
final class AppLinks
{
    /** The app's iOS bundle id and Android package (fixed once published). */
    public const APP_ID = 'om.muhra.app';

    /** The website paths' first segments the app has screens for; plus the homepage. */
    public const SECTIONS = [
        'horses-for-sale', 'tools-for-sale', 'transfer-board', 'farriers',
        'stables', 'clinics', 'centers', 'shops', 'events', 'blog', 'video-library',
    ];

    public const TEAM_ID_PATTERN = '/^[A-Z0-9]{10}$/';

    public const FINGERPRINT_PATTERN = '/^([0-9A-F]{2}:){31}[0-9A-F]{2}$/';

    /** @return array{apple_team_id: ?string, android_fingerprints: list<string>} */
    public static function settings(): array
    {
        $stored = self::stored();
        $team = strtoupper(trim((string) ($stored['apple_team_id'] ?? '')));

        return [
            'apple_team_id' => preg_match(self::TEAM_ID_PATTERN, $team) ? $team : null,
            'android_fingerprints' => self::fingerprints((string) ($stored['android_fingerprints'] ?? '')),
        ];
    }

    /** @param  array<string, mixed>  $state  the admin form's `links` state */
    public static function save(array $state): void
    {
        SiteSetting::set('mobile.links', json_encode([
            'apple_team_id' => strtoupper(trim((string) ($state['apple_team_id'] ?? ''))),
            'android_fingerprints' => implode("\n", self::fingerprints((string) ($state['android_fingerprints'] ?? ''))),
        ]), 'text', null, 'mobile_app');
    }

    /**
     * The SHA-256 certificate fingerprints in a text (one per line, or separated by commas/spaces),
     * upper case with colons; anything else is dropped.
     *
     * @return list<string>
     */
    public static function fingerprints(string $text): array
    {
        $found = [];

        foreach (preg_split('/[\s,]+/', strtoupper($text), -1, PREG_SPLIT_NO_EMPTY) as $value) {
            $value = preg_match('/^[0-9A-F]{64}$/', $value) ? implode(':', str_split($value, 2)) : $value;

            if (preg_match(self::FINGERPRINT_PATTERN, $value)) {
                $found[$value] = true;
            }
        }

        return array_keys($found);
    }

    /** `apple-app-site-association`, or null without the Apple Team ID. */
    public static function appleAssociation(): ?array
    {
        $team = self::settings()['apple_team_id'];

        if ($team === null) {
            return null;
        }

        $components = [
            // the forms' captcha images are not pages
            ['/' => '/*/captcha', 'exclude' => true],
            ['/' => '/'],
        ];

        foreach (self::SECTIONS as $section) {
            $components[] = ['/' => "/{$section}"];
            $components[] = ['/' => "/{$section}/*"];
        }

        return [
            'applinks' => [
                'details' => [
                    ['appIDs' => ["{$team}.".self::APP_ID], 'components' => $components],
                ],
            ],
        ];
    }

    /** `assetlinks.json`, or null without a signing certificate fingerprint. */
    public static function androidAssetLinks(): ?array
    {
        $fingerprints = self::settings()['android_fingerprints'];

        if ($fingerprints === []) {
            return null;
        }

        return [[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => self::APP_ID,
                'sha256_cert_fingerprints' => $fingerprints,
            ],
        ]];
    }

    /** @return array<string, mixed> */
    private static function stored(): array
    {
        $value = SiteSetting::get('mobile.links');
        $decoded = is_string($value) && $value !== '' ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
