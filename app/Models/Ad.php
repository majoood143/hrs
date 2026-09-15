<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class Ad extends Model
{
    use HasTranslations;

    protected $fillable = [
        'zone_id', 'customer_name', 'media_type', 'image_path', 'video_path',
        'alt_text', 'target_url', 'starts_at', 'ends_at', 'is_active', 'order', 'clicks',
    ];

    public array $translatable = ['alt_text'];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(AdZone::class, 'zone_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function getMediaPathAttribute(): ?string
    {
        return $this->media_type === 'video' ? $this->video_path : $this->image_path;
    }

    public function getStatusAttribute(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'scheduled';
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return 'expired';
        }

        return 'active';
    }
}
