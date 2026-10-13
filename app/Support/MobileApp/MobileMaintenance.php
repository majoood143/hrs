<?php

namespace App\Support\MobileApp;

use App\Models\SiteSetting;

/**
 * Closes the whole app with a message (admin → Mobile app → Maintenance), independent of the
 * website. Stored in the `mobile.maintenance` site setting. While it is on, the API also refuses
 * posting; browsing endpoints stay open (the website does not use them).
 */
final class MobileMaintenance
{
    public static function enabled(): bool
    {
        return (bool) (self::stored()['enabled'] ?? false);
    }

    /** @return array{enabled: bool, messages: array<string, ?string>} */
    public static function settings(): array
    {
        $stored = self::stored();
        $messages = [];

        foreach (MobileUpdates::locales() as $locale) {
            $text = trim((string) ($stored['messages'][$locale] ?? ''));
            $messages[$locale] = $text !== '' ? $text : null;
        }

        return ['enabled' => (bool) ($stored['enabled'] ?? false), 'messages' => $messages];
    }

    /** @param  array<string, mixed>  $state  the admin form's `maintenance` state */
    public static function save(array $state): void
    {
        SiteSetting::set('mobile.maintenance', json_encode([
            'enabled' => (bool) ($state['enabled'] ?? false),
            'messages' => (array) ($state['messages'] ?? []),
        ], JSON_UNESCAPED_UNICODE), 'text', null, 'mobile_app');
    }

    /** @return array{enabled: bool, message: ?string} */
    public static function forClient(): array
    {
        $settings = self::settings();

        return [
            'enabled' => $settings['enabled'],
            'message' => $settings['enabled'] ? ($settings['messages'][app()->getLocale()] ?? __('api.app.maintenance')) : null,
        ];
    }

    /** @return array<string, mixed> */
    private static function stored(): array
    {
        $value = SiteSetting::get('mobile.maintenance');
        $decoded = is_string($value) && $value !== '' ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
