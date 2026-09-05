<?php

namespace App\Services;

use App\Models\Setting;

class SettingsService
{
    /**
     * Create a new class instance.
     */
    protected $settings = [];

    public function get($key, $default = null)
    {
        if (empty($this->settings)) {
            $this->loadSettings();
        }

        return $this->settings[$key] ?? $default;
    }

    public function set($key, $value)
    {
        Setting::setValue($key, $value);
        $this->settings[$key] = $value;
    }

    public function loadSettings()
    {
        $this->settings = Setting::pluck('value', 'key')->toArray();
    }

    public function getSmtpSettings()
    {
        return [
            'host' => $this->get('mail_host', 'smtp.mailtrap.io'),
            'port' => $this->get('mail_port', '587'),
            'username' => $this->get('mail_username'),
            'password' => $this->get('mail_password'),
            'encryption' => $this->get('mail_encryption', 'tls'),
            'from_address' => $this->get('mail_from_address', 'noreply@example.com'),
            'from_name' => $this->get('mail_from_name', config('app.name')),
        ];
    }

    public function __construct()
    {
        //
    }
}
