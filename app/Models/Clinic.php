<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Clinic extends Model
{
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    protected $fillable = [
        'en_name',
        'ar_name',
        'slug',
        'country_id',
        'region_id',
        'city_id',
        'address',
        'map_link',
        'en_description',
        'ar_description',
        'cover_photo',
        'gallery',
        'opening_hours',
        'is_active',
    ];

    protected $casts = [
        'gallery' => 'array',
        'opening_hours' => 'array',
        'is_active' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(ClinicService::class, 'clinic_clinic_service');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }

    public function getDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'ar' ? $this->ar_description : $this->en_description;
    }

    public function getCoverPhotoUrlAttribute(): ?string
    {
        return $this->cover_photo ? Storage::disk('public')->url($this->cover_photo) : null;
    }

    public function getGalleryUrlsAttribute(): array
    {
        return collect($this->gallery ?? [])
            ->map(fn (string $path) => Storage::disk('public')->url($path))
            ->all();
    }
}
