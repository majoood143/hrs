<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A jockey or rider in the app's directory (posted from the app like farriers, or added by an admin).
 * Their racing record is not copied: `racing_jockey_id` points at the racing club's profile, read
 * live through RacingClient, and is shown only once an admin ticked `racing_verified`.
 */
class Jockey extends Model
{
    public const TYPES = ['flat_racing', 'endurance', 'show_jumping', 'dressage', 'other'];

    public const STATUSES = ['active', 'inactive', 'pending'];

    /** @return array<string, string> the riding types in the current language */
    public static function typeOptions(): array
    {
        return collect(self::TYPES)->mapWithKeys(fn (string $type) => [$type => __('jockeys.types.'.$type)])->all();
    }

    /** @return array<string, string> the statuses in the current language */
    public static function statusOptions(): array
    {
        return collect(self::STATUSES)->mapWithKeys(fn (string $status) => [$status => __('jockeys.statuses.'.$status)])->all();
    }

    protected $fillable = [
        'en_name',
        'ar_name',
        'types',
        'years_experience',
        'country_id',
        'region_id',
        'city_id',
        'cover_photo',
        'description_en',
        'description_ar',
        'contact_number',
        'racing_jockey_id',
        'racing_verified',
        'status',
    ];

    protected $casts = [
        'types' => 'array',
        'years_experience' => 'integer',
        'racing_jockey_id' => 'integer',
        'racing_verified' => 'boolean',
    ];

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

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /** @param  array<string, mixed>  $filters */
    public function scopeFilteredBy(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->whereJsonContains('types', $v))
            ->when((int) ($filters['region_id'] ?? 0), fn ($q, $v) => $q->where('region_id', $v))
            ->when((int) ($filters['city_id'] ?? 0), fn ($q, $v) => $q->where('city_id', $v))
            ->when($filters['country_id'] ?? null, fn ($q, $v) => $q->where('country_id', $v));
    }

    /** The verified racing profile id, else null. */
    public function getVerifiedRacingIdAttribute(): ?int
    {
        return $this->racing_verified ? $this->racing_jockey_id : null;
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
}
