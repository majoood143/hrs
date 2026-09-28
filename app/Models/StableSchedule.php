<?php

namespace App\Models;

use App\Models\Concerns\LogsStableActivity;
use App\Services\Stables\SlotGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A weekly pattern for one offering: on these weekdays, at these start times, from valid_from until
 * valid_until (open-ended when empty). SlotGenerator turns it into dated booking slots.
 *
 * weekdays are Carbon's day numbers (0 = Sunday … 6 = Saturday); start_times are "H:i".
 */
class StableSchedule extends Model
{
    use LogsStableActivity;

    protected $fillable = [
        'stable_id',
        'stable_offering_id',
        'weekdays',
        'start_times',
        'capacity',
        'trainer_id',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'start_times' => 'array',
            'capacity' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // its future slots without bookings go with it; booked ones stay as one-off slots
        static::deleting(function (self $schedule): void {
            $schedule->is_active = false;
            app(SlotGenerator::class)->sync($schedule);
        });
    }

    public function stable(): BelongsTo
    {
        return $this->belongsTo(Stable::class);
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(StableOffering::class, 'stable_offering_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(StableTrainer::class, 'trainer_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(BookingSlot::class, 'stable_schedule_id');
    }

    /** @return list<int> */
    public function weekdayNumbers(): array
    {
        return collect($this->weekdays ?? [])->map(fn ($day) => (int) $day)->filter(fn (int $day) => $day >= 0 && $day <= 6)->unique()->sort()->values()->all();
    }

    /** @return list<string> "H:i:s", sorted, without duplicates */
    public function startTimes(): array
    {
        return collect($this->start_times ?? [])
            ->map(fn ($time) => BookingSlot::normalizeTime(is_array($time) ? ($time['time'] ?? null) : $time))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /** @return list<string> */
    protected function stableActivityAttributes(): array
    {
        return ['stable_offering_id', 'weekdays', 'start_times', 'capacity', 'trainer_id', 'valid_from', 'valid_until', 'is_active'];
    }
}
