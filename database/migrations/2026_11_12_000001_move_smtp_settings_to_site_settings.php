<?php

use App\Models\SiteSetting;
use App\Support\SecretSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The SMTP settings move from the old key/value `settings` table (plain text) to site_settings
 * (the password encrypted). The plain-text password row is removed; the rest of the old table stays.
 */
return new class extends Migration
{
    private const KEYS = [
        'mail_host' => 'smtp.host',
        'mail_port' => 'smtp.port',
        'mail_username' => 'smtp.username',
        'mail_encryption' => 'smtp.encryption',
        'mail_from_address' => 'smtp.from_address',
        'mail_from_name' => 'smtp.from_name',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('settings') || ! Schema::hasTable('site_settings')) {
            return;
        }

        $old = DB::table('settings')->pluck('value', 'key');

        foreach (self::KEYS as $from => $to) {
            if (filled($old[$from] ?? null) && SiteSetting::query()->where('key', $to)->doesntExist()) {
                SiteSetting::set($to, (string) $old[$from], 'text', null, 'smtp');
            }
        }

        if (filled($old['mail_password'] ?? null) && SecretSetting::get('smtp.password') === '') {
            SecretSetting::set('smtp.password', (string) $old['mail_password'], 'smtp');
        }

        DB::table('settings')->where('key', 'mail_password')->delete();
        SiteSetting::clearCache();
    }

    public function down(): void
    {
        // the password was moved encrypted; nothing to put back in plain text
    }
};
