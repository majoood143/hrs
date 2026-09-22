<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Site settings that hold credentials (gateway keys, SMS passwords). They are encrypted
 * with the app key before they reach the database, so a database dump does not leak them.
 * A plain value saved before encryption existed still reads fine and is encrypted the next
 * time the settings page is saved.
 */
class SecretSetting
{
    private const PREFIX = 'enc:';

    public static function get(string $key, string $default = ''): string
    {
        $value = (string) SiteSetting::get($key, '');

        if ($value === '') {
            return $default;
        }

        if (! str_starts_with($value, self::PREFIX)) {
            return $value;
        }

        try {
            return Crypt::decryptString(substr($value, strlen(self::PREFIX)));
        } catch (DecryptException) {
            // Encrypted under another APP_KEY: better to look unset than to send garbage to a gateway.
            return $default;
        }
    }

    public static function set(string $key, ?string $value, ?string $group = null): void
    {
        $value = (string) $value;

        SiteSetting::set($key, $value === '' ? '' : self::PREFIX.Crypt::encryptString($value), 'text', null, $group);
    }
}
