<?php

namespace App\Support\MobileApp;

use App\Models\SiteSetting;

/**
 * The app's feature switches (admin → Mobile app → Features), stored in the `mobile.features` site
 * setting. Each feature: on/off, the platforms it is on for, the oldest app version that has it, and
 * what the app does while it is off (hide it, or show "Coming soon"). A feature nobody saved yet is
 * on everywhere, so a new key never switches anything off by itself.
 *
 * The app reads the result from `GET /api/v1/app-status`; the API also refuses posting while a
 * posting feature is off (`EnsureMobileFeature`).
 */
final class MobileFeatures
{
    public const ON = 'on';

    public const HIDDEN = 'hidden';

    public const SOON = 'soon';

    /** Feature keys by admin group (the order the admin page shows them). */
    public const GROUPS = [
        'marketplace' => ['horses_for_sale', 'tools_for_sale'],
        'directory' => ['stables', 'clinics', 'centers', 'shops', 'farriers', 'events', 'blog', 'videos'],
        'transfer' => ['transfer_board'],
        'posting' => ['post_horse', 'post_tool', 'post_farrier', 'post_transfer'],
        'other' => ['promos', 'contact_call', 'contact_whatsapp', 'language_switch'],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }

    /**
     * Every feature with its saved rule (defaults filled in), for the admin form.
     *
     * @return array<string, array{enabled: bool, platforms: list<string>, min_version: string|null, when_off: string}>
     */
    public static function rules(): array
    {
        $stored = self::stored();

        return collect(self::keys())->mapWithKeys(fn (string $key) => [$key => self::normalize($stored[$key] ?? [])])->all();
    }

    /**
     * What this app should do with each feature: "on", "hidden" or "soon".
     *
     * @return array<string, string>
     */
    public static function forClient(AppClient $client): array
    {
        return collect(self::rules())->map(fn (array $rule) => self::state($rule, $client))->all();
    }

    public static function enabledFor(string $key, AppClient $client): bool
    {
        return self::state(self::rules()[$key] ?? self::normalize([]), $client) === self::ON;
    }

    /** @param  array<string, mixed>  $rules  feature key => rule, as the admin form sends it */
    public static function save(array $rules): void
    {
        $clean = collect(self::keys())->mapWithKeys(fn (string $key) => [$key => self::normalize((array) ($rules[$key] ?? []))])->all();

        SiteSetting::set('mobile.features', json_encode($clean), 'text', null, 'mobile_app');
    }

    /** @param  array{enabled: bool, platforms: list<string>, min_version: string|null, when_off: string}  $rule */
    private static function state(array $rule, AppClient $client): string
    {
        $on = $rule['enabled'] && $client->onPlatform($rule['platforms']) && $client->atLeast($rule['min_version']);

        return $on ? self::ON : $rule['when_off'];
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array{enabled: bool, platforms: list<string>, min_version: string|null, when_off: string}
     */
    private static function normalize(array $rule): array
    {
        $platforms = array_values(array_intersect(AppClient::PLATFORMS, (array) ($rule['platforms'] ?? AppClient::PLATFORMS)));
        $min = trim((string) ($rule['min_version'] ?? ''));

        return [
            'enabled' => (bool) ($rule['enabled'] ?? true),
            'platforms' => $platforms,
            'min_version' => AppVersion::valid($min) ? $min : null,
            'when_off' => ($rule['when_off'] ?? null) === self::SOON ? self::SOON : self::HIDDEN,
        ];
    }

    /** @return array<string, mixed> */
    private static function stored(): array
    {
        $value = SiteSetting::get('mobile.features');
        $decoded = is_string($value) && $value !== '' ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
