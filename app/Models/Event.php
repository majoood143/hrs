<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

class Event extends Model
{
    use HasTranslations;

    public const DEFAULT_CATEGORY_COLOR = '#5c6259';

    protected $fillable = [
        'title', 'description', 'date', 'end_date', 'start_time', 'end_time', 'category_id', 'link',
    ];

    public array $translatable = ['title', 'description'];

    protected $casts = [
        'date' => 'date',
        'end_date' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->endingFrom(now()->toDateString());
    }

    /** Events still running on or after this day, so one already under way stays listed. */
    public function scopeEndingFrom(Builder $query, string $date): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereDate('date', '>=', $date)->orWhereDate('end_date', '>=', $date));
    }

    public function isMultiDay(): bool
    {
        return $this->end_date !== null && $this->end_date->gt($this->date);
    }

    public function lastDay(): Carbon
    {
        return $this->isMultiDay() ? $this->end_date : $this->date;
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
