<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuccessStory extends Model
{
    protected $fillable = [
        'horse_id', 'owner_name', 'en_route', 'ar_route', 'en_quote', 'ar_quote',
        'photo', 'is_published', 'order',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function horse(): BelongsTo
    {
        return $this->belongsTo(Horse::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->orderBy('order');
    }

    public function getRouteAttribute(): ?string
    {
        return app()->getLocale() === 'ar' ? $this->ar_route : $this->en_route;
    }

    public function getQuoteAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_quote : $this->en_quote;
    }
}
