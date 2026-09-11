<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TransferPost extends Model
{
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

    public function getCoverPhotoUrlAttribute(): ?string
    {
        return $this->cover_photo ? Storage::disk('public')->url($this->cover_photo) : null;
    }
}
