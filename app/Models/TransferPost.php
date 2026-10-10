<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TransferPost extends Model
{
    public const TYPES = ['offer', 'request'];

    /** Sort options (website `?sort=` and the mobile API); the first is the default. */
    public const SORTS = ['soonest', 'newest', 'price_asc', 'price_desc', 'capacity'];

    protected $fillable = [
        'type',
        'from_country_id',
        'from_region_id',
        'from_city_id',
        'to_country_id',
        'to_region_id',
        'to_city_id',
        'capacity',
        'transfer_date',
        'price',
        'cover_photo',
        'contact_number',
        'status',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'price' => 'decimal:3',
        'capacity' => 'integer',
    ];

    public function fromCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'from_country_id');
    }

    public function fromRegion(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'from_region_id');
    }

    public function fromCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'from_city_id');
    }

    public function toCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'to_country_id');
    }

    public function toRegion(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'to_region_id');
    }

    public function toCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'to_city_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('transfer_date', '>=', now()->toDateString());
    }

    /**
     * The listing filters, named like the website's query string (shared with the mobile API).
     * A price limit means nothing for requests (they carry no price), so it is ignored there.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilteredBy(Builder $query, array $filters): Builder
    {
        $type = in_array($filters['type'] ?? null, self::TYPES, true) ? $filters['type'] : null;

        return $query
            ->when($type, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['from_city_id'] ?? null, fn ($q, $v) => $q->where('from_city_id', $v))
            ->when($filters['to_city_id'] ?? null, fn ($q, $v) => $q->where('to_city_id', $v))
            ->when($filters['from_country_id'] ?? null, fn ($q, $v) => $q->where('from_country_id', $v))
            ->when($filters['to_country_id'] ?? null, fn ($q, $v) => $q->where('to_country_id', $v))
            ->when(filled($filters['date_from'] ?? null), fn ($q) => $q->whereDate('transfer_date', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn ($q) => $q->whereDate('transfer_date', '<=', $filters['date_to']))
            ->when($filters['capacity_min'] ?? null, fn ($q, $v) => $q->where('capacity', '>=', (int) $v))
            ->when(
                filled($filters['price_max'] ?? null) && $type !== 'request',
                fn ($q) => $q->where('price', '<=', (float) $filters['price_max'])
            );
    }

    public function scopeSortedBy(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'newest' => $query->latest('id'),
            'price_asc' => $query->orderByRaw('price is null, price asc'),
            'price_desc' => $query->orderByRaw('price is null, price desc'),
            'capacity' => $query->orderByDesc('capacity'),
            default => $query->orderBy('transfer_date'),
        };
    }

    public function getCoverPhotoUrlAttribute(): ?string
    {
        return $this->cover_photo ? Storage::disk('public')->url($this->cover_photo) : null;
    }
}
