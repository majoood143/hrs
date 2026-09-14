<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class Event extends Model
{
    use HasTranslations;

    public const DEFAULT_CATEGORY_COLOR = '#5c6259';

    protected $fillable = [
        'title', 'description', 'date', 'start_time', 'end_time', 'category_id', 'link',
    ];

    public array $translatable = ['title', 'description'];

    protected $casts = [
        'date' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('date', '>=', now()->toDateString());
    }

    public function categoryColor(): string
    {
        return $this->category?->color ?? self::DEFAULT_CATEGORY_COLOR;
    }

    public function categoryLabel(): string
    {
        return $this->category?->name ?? __('events.uncategorized');
    }

    public function isExternalLink(): bool
    {
        return (bool) preg_match('#^https?://#i', (string) $this->link);
    }

    public function isInternalLink(): bool
    {
        return filled($this->link) && ! $this->isExternalLink();
    }
}
