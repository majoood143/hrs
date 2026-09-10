<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Horse extends Model
{
    //
    protected $casts = [
        'attachment' => 'array',
        'dob' => 'date',
        'is_featured' => 'boolean',
    ];

    protected $fillable = ['en_name', 'ar_name', 'country_id', 'region_id', 'city_id', 'type_id', 'gender_id', 'user_id', 'dob', 'color_id', 'breed', 'microchip', 'registration_number', 'dam_id', 'sire_id', 'is_featured', 'cover_photo', 'public_story_en', 'public_story_ar'];


    use HasFactory;

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

    public function type(): BelongsTo
    {
        return $this->belongsTo(Type::class);
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function horse(): BelongsTo
    {
        return $this->belongsTo(Horse::class);
    }

    protected function age(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->dob ? Carbon::parse($this->dob)->age : null,
        );
    }

    public function attachement(): HasMany
    {
        return $this->hasMany(Attachement::class);
    }

    public function vaccination(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

    public function transaction(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function service(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function successStories(): HasMany
    {
        return $this->hasMany(SuccessStory::class);
    }

    /**
     * Featured horses safe for public display. Only ever selects
     * non-sensitive columns — never microchip, passport, or owner data.
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true)
            ->whereNotNull('cover_photo')
            ->with(['type', 'gender', 'country', 'city'])
            ->select(['id', 'en_name', 'ar_name', 'cover_photo', 'public_story_en', 'public_story_ar', 'type_id', 'gender_id', 'country_id', 'region_id', 'city_id', 'dob']);
    }

    public function getPublicStoryAttribute(): ?string
    {
        return app()->getLocale() === 'ar' ? $this->public_story_ar : $this->public_story_en;
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }

    public function getCoverPhotoUrlAttribute(): ?string
    {
        return $this->cover_photo ? Storage::disk('public')->url($this->cover_photo) : null;
    }
}
