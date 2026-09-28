<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * The admin WhatsApp alerts, as set in General Settings → WhatsApp: a master switch, the
 * sending instance, the admin numbers to message, and which kinds of new post trigger one.
 * The Evolution API URL and key are not here: the plugin reads them from .env (EVOLUTION_*).
 */
final class WhatsAppSettings
{
    /** Alert types that can be switched on/off one by one; all on until an admin says otherwise. */
    public const ALERTS = ['transfer_post', 'horse_sale_post', 'tool_sale_post', 'farrier', 'stable_registration'];

    public static function enabled(): bool
    {
        return (bool) SiteSetting::get('whatsapp.enabled', false);
    }

    /** The plugin's instance id (a UUID), or null to use the first connected instance. */
    public static function instanceId(): ?string
    {
        $id = trim((string) SiteSetting::get('whatsapp.instance_id', ''));

        return $id === '' ? null : $id;
    }

    /** @return array<int, array{name: string, phone: string}> as saved, for the settings form */
    public static function recipientRows(): array
    {
        return array_values(array_filter(self::json('whatsapp.recipients'), 'is_array'));
    }

    /** @return array<int, string> the admin numbers, normalized and without duplicates */
    public static function recipients(): array
    {
        $phones = array_map(fn (array $row) => PhoneNumber::normalize($row['phone'] ?? null), self::recipientRows());

        return array_values(array_unique(array_filter($phones)));
    }

    public static function alertEnabled(string $type): bool
    {
        return (bool) (self::json('whatsapp.alerts')[$type] ?? true);
    }

    /** @return array<string, bool> every alert type with its switch, for the settings form */
    public static function alerts(): array
    {
        return collect(self::ALERTS)->mapWithKeys(fn (string $type) => [$type => self::alertEnabled($type)])->all();
    }

    /** @return array<string, mixed> */
    public static function formState(): array
    {
        return [
            'enabled' => self::enabled(),
            'instance_id' => self::instanceId(),
            'recipients' => self::recipientRows(),
            'alerts' => self::alerts(),
        ];
    }

    /** @param  array<string, mixed>  $state  the settings form's `whatsapp` state */
    public static function save(array $state): void
    {
        $recipients = collect($state['recipients'] ?? [])
            ->map(fn ($row) => ['name' => trim((string) ($row['name'] ?? '')), 'phone' => trim((string) ($row['phone'] ?? ''))])
            ->filter(fn (array $row) => $row['phone'] !== '')
            ->values()
            ->all();

        $alerts = collect(self::ALERTS)
            ->mapWithKeys(fn (string $type) => [$type => (bool) ($state['alerts'][$type] ?? false)])
            ->all();

        SiteSetting::set('whatsapp.enabled', (bool) ($state['enabled'] ?? false), 'boolean', null, 'whatsapp');
        SiteSetting::set('whatsapp.instance_id', (string) ($state['instance_id'] ?? ''), 'text', null, 'whatsapp');
        SiteSetting::set('whatsapp.recipients', json_encode($recipients, JSON_UNESCAPED_UNICODE), 'text', null, 'whatsapp');
        SiteSetting::set('whatsapp.alerts', json_encode($alerts), 'text', null, 'whatsapp');
    }

    private static function json(string $key): array
    {
        $stored = SiteSetting::get($key);
        $decoded = is_string($stored) && $stored !== '' ? json_decode($stored, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
