<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Someone who orders services. Signs in with a phone number and an SMS code (App\Services\Auth\
 * CustomerOtpService) on the separate "customer" guard, so it has no password and no way into the admin.
 */
class Customer extends Authenticatable
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class)->latest();
    }

    /** +968 ••••4567: enough for them to recognise their own number, not to read it. */
    public function maskedPhone(): string
    {
        return static::mask($this->phone);
    }

    public static function mask(string $phone): string
    {
        // a full international number (country code + digits) keeps its 3-digit country code
        return strlen($phone) >= 10
            ? '+'.substr($phone, 0, 3).' ••••'.substr($phone, -4)
            : '••••'.substr($phone, -4);
    }

    public function displayName(): string
    {
        return $this->name ?: $this->maskedPhone();
    }
}
