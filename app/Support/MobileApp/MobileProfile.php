<?php

namespace App\Support\MobileApp;

use App\Models\SiteSetting;

/**
 * Which optional fields the app's Account screen shows (admin → Mobile app → Profile), stored in the
 * `mobile.profile` site setting and sent in `app-status.profile_fields`. The name is always shown (the
 * booking form needs it). The CRM's profile completion counts only the shown fields.
 */
final class MobileProfile
{
    /** App field => customers column (null: not part of the completion). */
    public const FIELDS = [
        'photo' => 'profile_photo',
        'email' => 'email',
        'gender' => 'gender',
        'date_of_birth' => 'date_of_birth',
        'national_id' => 'national_id',
        'country' => 'country_id',
        'region' => 'region_id',
        'marketing' => null,
    ];

    /** @return list<string> the shown fields, in FIELDS order (all of them until the admin saves) */
    public static function shown(): array
    {
        $value = SiteSetting::get('mobile.profile');
        $stored = is_string($value) && $value !== '' ? json_decode($value, true) : null;
        $fields = is_array($stored['fields'] ?? null) ? $stored['fields'] : array_keys(self::FIELDS);

        // a region is picked inside its country
        if (! in_array('country', $fields, true)) {
            $fields = array_diff($fields, ['region']);
        }

        return array_values(array_intersect(array_keys(self::FIELDS), $fields));
    }

    /** @return list<string> the customers columns the completion is over: the name + the shown fields */
    public static function completionColumns(): array
    {
        return ['name', ...array_values(array_filter(array_map(fn (string $f) => self::FIELDS[$f], self::shown())))];
    }

    /** @return array{fields: list<string>} */
    public static function settings(): array
    {
        return ['fields' => self::shown()];
    }

    /** @param  array<string, mixed>  $state  the admin form's `profile` state */
    public static function save(array $state): void
    {
        $fields = array_values(array_intersect(array_keys(self::FIELDS), (array) ($state['fields'] ?? [])));
        SiteSetting::set('mobile.profile', json_encode(['fields' => $fields]), 'text', null, 'mobile_app');
    }
}
