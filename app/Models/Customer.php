<?php

namespace App\Models;

use App\Support\MobileApp\MobileProfile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

/**
 * Someone who orders services. Signs in with a phone number and an SMS code (App\Services\Auth\
 * CustomerOtpService) on the separate "customer" guard, so it has no password and no way into the admin.
 * The app signs in the same way and gets a Sanctum token (guard "customer_api").
 */
class Customer extends Authenticatable
{
    use HasApiTokens;

    public const GENDERS = ['male', 'female'];

    protected $guarded = [];

    protected $hidden = ['national_id'];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'date_of_birth' => 'date',
            'national_id' => 'encrypted',
            'marketing_opt_in' => 'boolean',
            'games_blocked_at' => 'datetime',
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

    /** The country picked in the profile (a public one); `country` is the phone's, from the sign-in. */
    public function profileCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function profilePhotoUrl(): ?string
    {
        return $this->profile_photo ? Storage::disk('public')->url($this->profile_photo) : null;
    }

    public function events(): HasMany
    {
        return $this->hasMany(AppEvent::class);
    }

    /** How much of the optional profile is filled in, 0–100. */
    public function profileCompletion(): int
    {
        $fields = MobileProfile::completionColumns();
        $filled = collect($fields)->filter(fn (string $field) => filled($this->getAttribute($field)))->count();

        return (int) round($filled * 100 / count($fields));
    }

    /** ••••••4567: the last digits, for the admin and the app (never the whole number). */
    public function maskedNationalId(): ?string
    {
        $id = (string) $this->national_id;

        return $id === '' ? null : str_repeat('•', max(0, mb_strlen($id) - 4)).mb_substr($id, -4);
    }

    public function displayName(): string
    {
        return $this->name ?: $this->maskedPhone();
    }
}
