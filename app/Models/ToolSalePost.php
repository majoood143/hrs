<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ToolSalePost extends Model
{
    public const CATEGORIES = [
        'saddlery_tack',
        'grooming',
        'farrier_tools',
        'feeding_stable',
        'health_first_aid',
        'transport_equipment',
        'other',
    ];

    public const CONDITIONS = ['new', 'used'];

    protected $fillable = [
        'en_name',
        'ar_name',
        'category',
        'condition',
        'brand',
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

    public function getCategoryLabelAttribute(): string
    {
        return __('tools-for-sale.categories.' . $this->category);
    }

    public function getConditionLabelAttribute(): string
    {
        return __('tools-for-sale.conditions.' . $this->condition);
    }
}
