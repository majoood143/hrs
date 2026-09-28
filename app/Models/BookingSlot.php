<?php

namespace App\Models;

use App\Enums\StableBookingStatus;
use App\Models\Concerns\LogsStableActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * One bookable time of one offering on one date, with room for `capacity` riders. The places taken
 * are counted live from the bookings that hold them (pending checkouts included), never kept in a
 * counter that could drift.
 *
 * date/start_time/end_time are the stable's wall-clock time (config stable_bookings.timezone). They
 * are stored as plain strings ("Y-m-d", "H:i:s") so MySQL and the sqlite tests compare them alike.
 */
class BookingSlot extends Model
{
    use LogsStableActivity;

    /** @var list<string> */
    protected static $recordEvents = ['updated'];

    protected $fillable = [
        'stable_id',
        'stable_offering_id',
        'stable_schedule_id',
        'date',
        'start_time',
        'end_time',
        'capacity',
        'is_open',
        'trainer_id',
        'notes',
    ];

    protected $attributes = [
        'is_open' => true,
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'capacity' => 'integer',
            'is_open' => 'boolean',
        ];
    }

    public static function timezone(): string
    {
        return (string) config('stable_bookings.timezone', 'Asia/Muscat');
    }

    /** Today at the stables, as "Y-m-d". */
    public static function today(): string
    {
        return CarbonImmutable::now(self::timezone())->toDateString();
    }

    /** "8:00", "08:00" or "08:00:00" as "08:00:00"; null for anything that is not a time of day. */
    public static function normalizeTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }

        if (! is_string($value) || ! preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($value), $m)) {
            return null;
        }

        [$h, $i, $s] = [(int) $m[1], (int) $m[2], (int) ($m[3] ?? 0)];

        return $h < 24 && $i < 60 && $s < 60 ? sprintf('%02d:%02d:%02d', $h, $i, $s) : null;
    }

    public function setDateAttribute(mixed $value): void
    {
        $this->attributes['date'] = $value === null ? null : Carbon::parse($value)->toDateString();
    }

    public function setStartTimeAttribute(mixed $value): void
    {
        $this->attributes['start_time'] = self::normalizeTime($value);
    }

    public function setEndTimeAttribute(mixed $value): void
    {
        $this->attributes['end_time'] = self::normalizeTime($value);
    }

    public function stable(): BelongsTo
    {
        return $this->belongsTo(Stable::class);
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(StableOffering::class, 'stable_offering_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(StableSchedule::class, 'stable_schedule_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(StableTrainer::class, 'trainer_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(StableBooking::class);
    }

    public function holdingBookings(): HasMany
    {
        return $this->bookings()->whereIn('status', StableBookingStatus::holdingValues());
    }

    /** Adds `booked_riders`: the places taken, in the same query. */
    public function scopeWithBookedRiders(Builder $query): Builder
    {
        return $query->withSum('holdingBookings as booked_riders', 'riders');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('date', '>=', self::today());
    }

    public function bookedRiders(): int
    {
        if (array_key_exists('booked_riders', $this->attributes)) {
            return (int) $this->attributes['booked_riders'];
        }

        return (int) $this->holdingBookings()->sum('riders');
    }

    public function remainingPlaces(): int
    {
        return max(0, (int) $this->capacity - $this->bookedRiders());
    }

    public function hasBookings(): bool
    {
        return $this->bookedRiders() > 0;
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date->toDateString().' '.$this->attributes['start_time'], self::timezone());
    }

    /** The end, on the next day when the slot runs past midnight. */
    public function endsAt(): CarbonImmutable
    {
        $end = CarbonImmutable::parse($this->date->toDateString().' '.$this->attributes['end_time'], self::timezone());

        return $end->lessThanOrEqualTo($this->startsAt()) ? $end->addDay() : $end;
    }

    public function bookingClosesAt(): CarbonImmutable
    {
        return $this->startsAt()->subMinutes((int) ($this->offering?->booking_cutoff_minutes ?? 0));
    }

    public function hasStarted(): bool
    {
        return $this->startsAt()->isPast();
    }

    public function timeRange(): string
    {
        try {
            return $this->startsAt()->format('H:i').' – '.$this->endsAt()->format('H:i');
        } catch (Throwable) {
            return '';
        }
    }

    /** @return list<string> */
    protected function stableActivityAttributes(): array
    {
        return ['capacity', 'is_open', 'trainer_id', 'notes'];
    }
}
