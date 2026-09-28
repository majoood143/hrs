<?php

namespace App\Services\Stables;

use App\Models\BookingSlot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The slots a customer can book right now: open, of an active service, at a stable that is live
 * and has its commission agreed, not yet past the booking cutoff, and with enough places left for
 * the riders asked for. Used by the search page and each service's page.
 */
class SlotFinder
{
    public const TIMES_OF_DAY = ['morning', 'afternoon', 'evening'];

    /**
     * @param  array{region_id?: ?int, city_id?: ?int, type?: ?string, stable_id?: ?int, offering_id?: ?int, time?: ?string}  $filters
     * @return Collection<int, BookingSlot> sorted by date and time
     */
    public function between(string $from, string $to, int $riders = 1, array $filters = []): Collection
    {
        $riders = max(1, $riders);

        return BookingSlot::query()
            ->withBookedRiders()
            ->with(['offering', 'stable.city', 'stable.region', 'trainer'])
            ->whereBetween('date', [$from, $to])
            ->where('is_open', true)
            ->when($filters['offering_id'] ?? null, fn (Builder $q, $id) => $q->where('stable_offering_id', $id))
            ->when($filters['stable_id'] ?? null, fn (Builder $q, $id) => $q->where('stable_id', $id))
            ->whereHas('offering', fn (Builder $q) => $q
                ->where('is_active', true)
                ->where('min_riders', '<=', $riders)
                ->where('max_riders', '>=', $riders)
                ->when($filters['type'] ?? null, fn (Builder $q, $type) => $q->where('type', $type)))
            ->whereHas('stable', fn (Builder $q) => $q
                ->active()
                ->whereNotNull('commission_type')
                ->whereNotNull('commission_value')
                ->when($filters['region_id'] ?? null, fn (Builder $q, $id) => $q->where('region_id', $id))
                ->when($filters['city_id'] ?? null, fn (Builder $q, $id) => $q->where('city_id', $id)))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn (BookingSlot $slot) => $slot->remainingPlaces() >= $riders
                && $slot->bookingClosesAt()->isFuture()
                && $this->matchesTimeOfDay($slot, $filters['time'] ?? null))
            ->values();
    }

    /** @return Collection<int, BookingSlot> */
    public function on(string $date, int $riders = 1, array $filters = []): Collection
    {
        return $this->between($date, $date, $riders, $filters);
    }

    /** The first day after $date (within $days) with something bookable, or null. */
    public function nextAvailableDate(string $date, int $riders = 1, array $filters = [], int $days = 30): ?string
    {
        $from = CarbonImmutable::parse($date)->addDay()->toDateString();
        $to = CarbonImmutable::parse($date)->addDays($days)->toDateString();

        return $this->between($from, $to, $riders, $filters)->first()?->date->toDateString();
    }

    private function matchesTimeOfDay(BookingSlot $slot, ?string $time): bool
    {
        if (! in_array($time, self::TIMES_OF_DAY, true)) {
            return true;
        }

        $hour = (int) $slot->startsAt()->format('G');

        return match ($time) {
            'morning' => $hour < 12,
            'afternoon' => $hour >= 12 && $hour < 17,
            default => $hour >= 17,
        };
    }
}
