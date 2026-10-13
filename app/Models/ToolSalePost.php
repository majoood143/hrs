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

    /** The listing's sort options (website `?sort=` and the mobile API); the first is the default. */
    public const SORTS = ['newest', 'price_asc', 'price_desc'];

    public const STATUSES = ['active', 'sold', 'expired', 'cancelled', 'pending'];

    /** @return array<string, string> the categories in the current language */
    public static function categoryOptions(): array
    {
        return collect(self::CATEGORIES)->mapWithKeys(fn (string $category) => [$category => __('tools-for-sale.categories.'.$category)])->all();
    }

    /** @return array<string, string> */
    public static function conditionOptions(): array
    {
        return collect(self::CONDITIONS)->mapWithKeys(fn (string $condition) => [$condition => __('tools-for-sale.conditions.'.$condition)])->all();
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return collect(self::STATUSES)->mapWithKeys(fn (string $status) => [$status => __('admin_tool_sale_post.statuses.'.$status)])->all();
    }

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
        'price_negotiable',
        'cover_photo',
        'description_en',
        'description_ar',
        'contact_number',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:3',
        'price_negotiable' => 'boolean',
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
     * Unknown categories and conditions are ignored, as on the website.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilteredBy(Builder $query, array $filters): Builder
    {
        $category = in_array($filters['category'] ?? null, self::CATEGORIES, true) ? $filters['category'] : null;
        $condition = in_array($filters['condition'] ?? null, self::CONDITIONS, true) ? $filters['condition'] : null;

        return $query
            ->when($category, fn ($q, $v) => $q->where('category', $v))
            ->when($condition, fn ($q, $v) => $q->where('condition', $v))
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

    public function getCategoryLabelAttribute(): string
    {
        return __('tools-for-sale.categories.'.$this->category);
    }

    public function getConditionLabelAttribute(): string
    {
        return __('tools-for-sale.conditions.'.$this->condition);
    }
}
