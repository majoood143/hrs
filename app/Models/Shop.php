<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Shop extends Model
{
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public const TYPES = [
        'tack' => 'Tack',
        'feed_supplements' => 'Feed & Supplements',
        'equipment' => 'Equipment',
        'saddlery' => 'Saddlery',
    ];

    public const DELIVERY_SCOPES = [
        'local' => 'Local Delivery',
        'international' => 'International Delivery',
        'both' => 'Local & International Delivery',
    ];

    public const PAYMENT_OPTIONS = [
        'cash' => 'Cash',
        'card' => 'Card',
        'bank_transfer' => 'Bank Transfer',
        'apple_pay' => 'Apple Pay',
        'online_payment' => 'Online Payment',
        'cash_on_delivery' => 'Cash on Delivery',
    ];

    protected $fillable = [
        'en_name',
        'ar_name',
        'type',
        'slug',
        'country_id',
        'region_id',
        'city_id',
        'address',
        'map_link',
        'phone',
        'website_url',
        'instagram_url',
        'en_description',
        'ar_description',
        'cover_photo',
        'gallery',
        'opening_hours',
        'is_active',
        'is_online',
        'delivery_scope',
        'payment_options',
    ];

    protected $casts = [
        'gallery' => 'array',
        'opening_hours' => 'array',
        'is_active' => 'boolean',
        'is_online' => 'boolean',
        'payment_options' => 'array',
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
        return $this->belongsToMany(ShopService::class, 'shop_shop_service');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return $query->when($type, fn (Builder $q) => $q->where('type', $type));
    }

    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('is_online', true);
    }

    public function getTypeLabelAttribute(): string
    {
        return __('shops.types.' . $this->type);
    }

    public function getDeliveryScopeLabelAttribute(): ?string
    {
        return $this->delivery_scope ? __('shops.delivery_scopes.' . $this->delivery_scope) : null;
    }

    public function getPaymentOptionLabelsAttribute(): array
    {
        return collect($this->payment_options ?? [])
            ->map(fn (string $option) => __('shops.payment_options_list.' . $option))
            ->all();
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
