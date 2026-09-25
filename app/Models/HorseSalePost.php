<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class HorseSalePost extends Model
{
    protected $fillable = [
        'en_name',
        'ar_name',
        'type_id',
        'gender_id',
        'color_id',
        'breed',
        'dob',
        'dam',
        'sire',
        'birth_country_id',
        'passport_number',
        'passport_document',
        'country_id',
        'region_id',
        'city_id',
        'price',
        'price_negotiable',
        'cover_photo',
        'images',
        'description_en',
        'description_ar',
        'contact_number',
        'status',
    ];

    protected $casts = [
        'dob' => 'date',
        'price' => 'decimal:3',
        'price_negotiable' => 'boolean',
        'images' => 'array',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(Type::class);
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function birthCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'birth_country_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }

    public function getDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'ar' ? $this->description_ar : $this->description_en;
    }

    public function getCoverPhotoUrlAttribute(): ?string
    {
        return $this->cover_photo ? Storage::disk('public')->url($this->cover_photo) : null;
    }

    public function getPassportDocumentUrlAttribute(): ?string
    {
        return $this->passport_document ? Storage::disk('public')->url($this->passport_document) : null;
    }

    public function getGalleryImageUrlsAttribute(): array
    {
        return collect($this->images ?? [])
            ->map(fn (string $path): string => Storage::disk('public')->url($path))
            ->prepend($this->cover_photo_url)
            ->filter()
            ->values()
            ->all();
    }

    public function getAgeAttribute(): ?int
    {
        return $this->dob ? Carbon::parse($this->dob)->age : null;
    }

    public function getAgeInMonthsAttribute(): ?int
    {
        return $this->dob ? (int) Carbon::parse($this->dob)->diffInMonths(now()) : null;
    }
}
