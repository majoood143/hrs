<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Config;

/**
 * The SMTP server from admin → SMTP Settings (site settings, the password encrypted like the
 * gateway keys). Applied at boot when a host and username are set; the mailer itself must be
 * `smtp` in `.env`.
 */
final class MailServer
{
    /** @return array{host: string, port: string, username: string, password: string, encryption: string, from_address: string, from_name: string} */
    public static function settings(): array
    {
        return [
            'host' => (string) SiteSetting::get('smtp.host', ''),
            'port' => (string) SiteSetting::get('smtp.port', '587'),
            'username' => (string) SiteSetting::get('smtp.username', ''),
            'password' => SecretSetting::get('smtp.password'),
            'encryption' => (string) SiteSetting::get('smtp.encryption', 'tls'),
            'from_address' => (string) SiteSetting::get('smtp.from_address', ''),
            'from_name' => (string) SiteSetting::get('smtp.from_name', ''),
        ];
    }

    /** @param  array<string, string>  $settings  as from settings() */
    public static function apply(array $settings): void
    {
        Config::set([
            'mail.mailers.smtp.host' => $settings['host'],
            'mail.mailers.smtp.port' => (int) ($settings['port'] ?: 587),
            'mail.mailers.smtp.username' => $settings['username'],
            'mail.mailers.smtp.password' => $settings['password'],
            'mail.mailers.smtp.encryption' => $settings['encryption'] ?: null,
            'mail.from.address' => $settings['from_address'] ?: config('mail.from.address'),
            'mail.from.name' => $settings['from_name'] ?: config('mail.from.name'),
        ]);
    }
}
