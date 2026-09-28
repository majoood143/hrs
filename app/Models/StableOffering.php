<?php

namespace App\Models;

use App\Enums\StableOfferingType;
use App\Models\Concerns\LogsStableActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Something a stable sells by the slot, e.g. a riding lesson. The price is per rider; capacity is the
 * default number of riders per slot (1 makes it a private lesson). Bookings close
 * booking_cutoff_minutes before a slot starts.
 */
class StableOffering extends Model
{
    use LogsStableActivity;

    protected $fillable = [
        'stable_id',
        'type',
        'en_name',
        'ar_name',
        'en_description',
        'ar_description',
        'photo',
        'price',
        'duration_minutes',
        'capacity',
        'min_riders',
        'max_riders',
        'booking_cutoff_minutes',
        'is_active',
    ];

    protected $attributes = [
        'type' => 'riding_training',
        'min_riders' => 1,
        'max_riders' => 1,
        'booking_cutoff_minutes' => 60,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'type' => StableOfferingType::class,
            'price' => 'decimal:3',
            'duration_minutes' => 'integer',
            'capacity' => 'integer',
            'min_riders' => 'integer',
            'max_riders' => 'integer',
            'booking_cutoff_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function stable(): BelongsTo
    {
        return $this->belongsTo(Stable::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(StableSchedule::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(BookingSlot::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' && filled($this->ar_name) ? $this->ar_name : (string) $this->en_name;
    }

    public function getDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'ar' && filled($this->ar_description) ? $this->ar_description : $this->en_description;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? Storage::disk('public')->url($this->photo) : null;
    }

    /** @return list<string> */
    protected function stableActivityAttributes(): array
    {
        return ['type', 'en_name', 'ar_name', 'en_description', 'ar_description', 'photo', 'price', 'duration_minutes', 'capacity', 'min_riders', 'max_riders', 'booking_cutoff_minutes', 'is_active'];
    }
}
