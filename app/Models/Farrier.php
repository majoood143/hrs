<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Farrier extends Model
{
    /** Sort options (website `?sort=` and the mobile API); the first is the default. */
    public const SORTS = ['newest', 'price_asc', 'price_desc'];

    protected $fillable = [
        'en_name',
        'ar_name',
        'specialty',
        'years_experience',
        'country_id',
        'region_id',
        'city_id',
        'price',
        'cover_photo',
        'description_en',
        'description_ar',
        'contact_number',
        'status',
    ];

    protected $casts = [
        'years_experience' => 'integer',
        'price' => 'decimal:3',
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

    /**
     * The listing filters, named like the website's query string (shared with the mobile API).
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilteredBy(Builder $query, array $filters): Builder
    {
        return $query
            ->when(filled($filters['specialty'] ?? null), fn ($q) => $q->where('specialty', 'like', '%'.$filters['specialty'].'%'))
            ->when((int) ($filters['region_id'] ?? 0), fn ($q, $v) => $q->where('region_id', $v))
            ->when((int) ($filters['city_id'] ?? 0), fn ($q, $v) => $q->where('city_id', $v))
            ->when($filters['country_id'] ?? null, fn ($q, $v) => $q->where('country_id', $v))
            ->when(filled($filters['price_max'] ?? null), fn ($q) => $q->where('price', '<=', (float) $filters['price_max']));
    }

    public function scopeSortedBy(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            default => $query->latest('id'),
        };
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
