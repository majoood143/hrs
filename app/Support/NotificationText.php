<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * The words of the messages customers receive (SMS texts, email subjects and lines). An admin can
 * rewrite them in the admin ("Notification Texts"); what they save is kept as an override per text and
 * per language, and anything not overridden falls back to lang/{locale}/notifications.php.
 *
 * Keys are those of that lang file (e.g. "sms.received_paid", "mail.completed.intro"). Placeholders are
 * Laravel's ":name". Only customer-facing texts are editable; the emails to reviewers are not listed here.
 */
class NotificationText
{
    private const SETTING = 'notifications.texts';

    /** The texts an admin may edit, in the order the admin page lists them: group => keys. */
    public const EDITABLE = [
        'sms' => ['sms.received_paid', 'sms.received_free', 'sms.completed', 'sms.rejected', 'sms.refunded'],
        'received' => ['mail.received.subject_paid', 'mail.received.subject_free', 'mail.received.intro_paid', 'mail.received.intro_free', 'mail.received.receipt_attached', 'mail.received.next'],
        'completed' => ['mail.completed.subject', 'mail.completed.intro', 'mail.completed.next'],
        'rejected' => ['mail.rejected.subject', 'mail.rejected.intro', 'mail.rejected.reason', 'mail.rejected.refund'],
        'refunded' => ['mail.refunded.subject', 'mail.refunded.intro', 'mail.refunded.timing', 'mail.refunded.fee_kept'],
        'common' => ['mail.greeting', 'mail.greeting_guest', 'mail.track_button'],
    ];

    /** Placeholders a text may lose without harm: the rest of them carry what the customer needs to know. */
    private const OPTIONAL_PLACEHOLDERS = ['site', 'service'];

    /**
     * The text for a key in a language: the admin's override if there is one, else the language file's.
     *
     * @param  array<string, mixed>  $replace
     */
    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $override = static::overrides()[$key][$locale] ?? null;

        if (is_string($override) && trim($override) !== '') {
            return static::replace($override, $replace);
        }

        return (string) __('notifications.'.$key, $replace, $locale);
    }

    /** @return array<string, array<string, string>> key => [locale => text] */
    public static function overrides(): array
    {
        $stored = SiteSetting::get(self::SETTING);
        $decoded = is_string($stored) && $stored !== '' ? json_decode($stored, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    /** The text as written in the language file, ignoring overrides. */
    public static function defaultText(string $key, string $locale): string
    {
        return (string) trans('notifications.'.$key, [], $locale);
    }

    /**
     * Save the overrides. A text equal to its default (or empty) is not stored, so a reset really is
     * a reset and a later change to the language file still reaches everyone who never edited it.
     *
     * validate() is enforced here too, not only by the admin form's field-level rule: that rule is
     * the only thing stopping a bad text today, so a caller that reaches this method some other way
     * (tinker, a seeder, a future API) could otherwise store a text that drops the order number, the
     * link or the amount. An override that fails validation is simply not stored (as if it were never
     * changed) rather than thrown, so a batch save of several texts still saves the valid ones.
     *
     * @param  array<string, array<string, ?string>>  $texts  key => [locale => text]
     */
    public static function save(array $texts): void
    {
        $keep = [];

        foreach ($texts as $key => $perLocale) {
            foreach ($perLocale as $locale => $text) {
                $text = trim((string) $text);

                if ($text !== '' && $text !== static::defaultText($key, $locale) && static::validate($key, $text) === null) {
                    $keep[$key][$locale] = $text;
                }
            }
        }

        SiteSetting::set(self::SETTING, $keep === [] ? '' : json_encode($keep, JSON_UNESCAPED_UNICODE), 'text', null, 'notifications');
    }

    public static function reset(): void
    {
        SiteSetting::set(self::SETTING, '', 'text', null, 'notifications');
    }

    /** @return list<string> the placeholders a key's default texts use (":number" as "number") */
    public static function placeholders(string $key): array
    {
        $found = [];

        foreach (array_keys(config('languages.available', ['en' => []])) as $locale) {
            preg_match_all('/:([A-Za-z_]+)/', static::defaultText($key, $locale), $matches);
            $found = [...$found, ...$matches[1]];
        }

        return array_values(array_unique($found));
    }

    /**
     * Why a text may not be saved for a key, or null when it is fine: it must keep the placeholders that carry
     * the order number, the link, the amount... (every default placeholder except the site and service
     * names), and may not invent placeholders that would never be filled in.
     */
    public static function validate(string $key, string $text): ?string
    {
        if (trim($text) === '') {
            return null;
        }

        $allowed = static::placeholders($key);
        preg_match_all('/(?<![A-Za-z0-9]):([a-z_]+)/', $text, $used);
        $used = array_unique($used[1]);

        $unknown = array_values(array_diff($used, $allowed));

        if ($unknown !== []) {
            return __('admin_notification_texts.errors.unknown', ['names' => ':'.implode(', :', $unknown), 'allowed' => $allowed === [] ? '—' : ':'.implode(', :', $allowed)]);
        }

        $missing = array_values(array_diff($allowed, self::OPTIONAL_PLACEHOLDERS, $used));

        if ($missing !== []) {
            return __('admin_notification_texts.errors.missing', ['names' => ':'.implode(', :', $missing)]);
        }

        return null;
    }

    /** @param  array<string, mixed>  $replace */
    private static function replace(string $text, array $replace): string
    {
        // strtr tries the longest key first, so ":numbers" never gets clipped by ":number"
        return strtr($text, collect($replace)->mapWithKeys(fn ($value, $name) => [':'.$name => (string) $value])->all());
    }
}
