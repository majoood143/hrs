<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    //
    protected $fillable = [
        'en_name', 'ar_name', 'is_public',
        'country_code', 'phone_code',
        'currency_code', 'nationality_en', 'nationality_ar', 'order',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function region():HasMany
    {
        return $this->hasMany(Region::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }

    public function setCountryCodeAttribute(?string $value): void
    {
        $this->attributes['country_code'] = $value ? strtoupper($value) : null;
    }

    public function getFlagUrlAttribute(): ?string
    {
        if (! $this->country_code) {
            return null;
        }

        return asset('flags/' . strtolower($this->country_code) . '.svg');
    }

    public function getPhoneCodeFormattedAttribute(): ?string
    {
        return $this->phone_code ? '+' . ltrim($this->phone_code, '+') : null;
    }

    public function setCurrencyCodeAttribute(?string $value): void
    {
        $this->attributes['currency_code'] = $value ? strtoupper($value) : null;
    }

    public function getNationalityAttribute(): ?string
    {
        return app()->getLocale() === 'ar' ? $this->nationality_ar : $this->nationality_en;
    }
}
