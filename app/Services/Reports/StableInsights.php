<?php

namespace App\Services\Reports;

use App\Enums\StableBookingStatus;
use App\Models\BookingSlot;
use App\Models\ServiceOrder;
use App\Models\Stable;
use App\Models\StableBooking;
use App\Models\StableReview;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * How a stable (or, with no stable, every stable) is doing over a period: the money by day, week
 * or month, the same figures for the period just before (for the deltas), when its slots fill up,
 * which services sell, new against returning customers, cancellations, no-shows and ratings.
 *
 * Money follows the statements exactly (StableLedgerRow, dated the day it was paid or attended);
 * everything about sessions is dated by the session's day.
 */
class StableInsights
{
    /** @var Collection<int, StableLedgerRow>|null */
    private ?Collection $rows = null;

    private ?self $previous = null;

    public function __construct(
        public readonly ?Stable $stable,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    public static function forPeriod(?Stable $stable, string $period, ?string $from = null, ?string $to = null): self
    {
        [$start, $end] = IncomeStatement::period($period, $from, $to);

        return new self($stable, $start, $end);
    }

    /** The same number of days, just before. */
    public function previous(): self
    {
        $days = (int) $this->from->diffInDays($this->to) + 1;

        return $this->previous ??= new self($this->stable, $this->from->subDays($days)->startOfDay(), $this->from->subDay()->endOfDay());
    }

    /** @return Collection<int, StableLedgerRow> */
    public function rows(): Collection
    {
        return $this->rows ??= StableStatement::ordersQuery($this->stable)
            ->get()
            ->map(fn (ServiceOrder $order) => StableLedgerRow::fromOrder($order))
            ->filter(fn (StableLedgerRow $row) => $row->date->betweenIncluded($this->from, $this->to))
            ->values();
    }

    /**
     * @return array{collected: int, stable_share: int, commission: int, fee: int, our_share: int, bookings: int, riders: int}
     */
    public function money(): array
    {
        $rows = $this->rows();

        return [
            'collected' => (int) $rows->sum(fn (StableLedgerRow $r) => $r->collected()),
            'stable_share' => (int) $rows->sum(fn (StableLedgerRow $r) => $r->stableShare()),
            'commission' => (int) $rows->sum(fn (StableLedgerRow $r) => $r->commission + $r->vatOnCommission),
            'fee' => (int) $rows->sum(fn (StableLedgerRow $r) => $r->fee + $r->vatOnFee),
            'our_share' => (int) $rows->sum(fn (StableLedgerRow $r) => $r->ourShare()),
            'bookings' => $rows->filter(fn (StableLedgerRow $r) => $r->bookingReference !== null)->count(),
            'riders' => (int) $rows->sum(fn (StableLedgerRow $r) => $r->riders),
        ];
    }

    /** "day" up to a month, "week" up to four months, else "month". */
    public function bucket(): string
    {
        $days = (int) $this->from->diffInDays($this->to) + 1;

        return $days <= 31 ? 'day' : ($days <= 124 ? 'week' : 'month');
    }

    /**
     * The money per day/week/month, every bucket of the period present (zero when nothing sold).
     *
     * @return Collection<string, array{label: string, stable_share: int, commission: int, our_share: int, collected: int}>
     */
    public function series(): Collection
    {
        $bucket = $this->bucket();
        $key = fn (CarbonImmutable $date): string => match ($bucket) {
            'day' => $date->toDateString(),
            'week' => $date->startOfWeek()->toDateString(),
            default => $date->format('Y-m'),
        };

        $series = collect();

        for ($cursor = $this->from; $cursor->lte($this->to); $cursor = match ($bucket) {
            'day' => $cursor->addDay(),
            'week' => $cursor->startOfWeek()->addWeek(),
            default => $cursor->startOfMonth()->addMonth(),
        }) {
            $series->put($key($cursor), [
                'label' => match ($bucket) {
                    'day' => $cursor->locale(app()->getLocale())->translatedFormat('j M'),
                    'week' => $cursor->startOfWeek()->locale(app()->getLocale())->translatedFormat('j M'),
                    default => $cursor->locale(app()->getLocale())->translatedFormat('M Y'),
                },
                'stable_share' => 0, 'commission' => 0, 'our_share' => 0, 'collected' => 0,
            ]);
        }

        foreach ($this->rows() as $row) {
            $k = $key($row->date);

            if (! $series->has($k)) {
                continue;
            }

            $entry = $series->get($k);
            $entry['stable_share'] += $row->stableShare();
            $entry['commission'] += $row->commission + $row->vatOnCommission;
            $entry['our_share'] += $row->ourShare();
            $entry['collected'] += $row->collected();
            $series->put($k, $entry);
        }

        return $series;
    }

    /** @return Builder<StableBooking> bookings whose session falls in the period */
    private function sessionBookings(): Builder
    {
        return StableBooking::query()
            ->when($this->stable, fn (Builder $q, Stable $stable) => $q->where('stable_bookings.stable_id', $stable->getKey()))
            ->whereHas('slot', fn (Builder $q) => $q->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()]));
    }

    /**
     * Places sold against places open, by weekday and start hour, over the sessions of the period
     * that have already started. Closed slots are left out (nothing could be sold).
     *
     * @return array{hours: list<int>, days: list<int>, cells: array<int, array<int, array{booked: int, capacity: int}>>, booked: int, capacity: int}
     */
    public function occupancy(): array
    {
        $end = min($this->to->toDateString(), BookingSlot::today());

        $slots = BookingSlot::query()
            ->when($this->stable, fn (Builder $q, Stable $stable) => $q->where('stable_id', $stable->getKey()))
            ->whereBetween('date', [$this->from->toDateString(), $end])
            ->where('is_open', true)
            ->withBookedRiders()
            ->get()
            ->filter(fn (BookingSlot $slot) => $slot->hasStarted());

        $cells = [];

        foreach ($slots as $slot) {
            $day = (int) $slot->date->dayOfWeek;
            $hour = (int) substr((string) $slot->getRawOriginal('start_time'), 0, 2);
            $cells[$day][$hour]['booked'] = ($cells[$day][$hour]['booked'] ?? 0) + min($slot->bookedRiders(), $slot->capacity);
            $cells[$day][$hour]['capacity'] = ($cells[$day][$hour]['capacity'] ?? 0) + $slot->capacity;
        }

        $hours = collect($cells)->flatMap(fn (array $byHour) => array_keys($byHour))->unique()->sort()->values()->all();

        return [
            'hours' => $hours,
            'days' => [0, 1, 2, 3, 4, 5, 6],
            'cells' => $cells,
            'booked' => (int) collect($cells)->flatten(1)->sum('booked'),
            'capacity' => (int) collect($cells)->flatten(1)->sum('capacity'),
        ];
    }

    /** Sold places as a share of open places, 0–100, or null with nothing open. */
    public function occupancyRate(): ?int
    {
        $o = $this->occupancy();

        return $o['capacity'] > 0 ? (int) round($o['booked'] * 100 / $o['capacity']) : null;
    }

    /**
     * What sold, by service or package: money from the statement rows.
     *
     * @return Collection<int, array{name: string, count: int, riders: int, collected: int, stable_share: int, commission: int}>
     */
    public function topServices(int $limit = 8): Collection
    {
        return $this->rows()
            ->groupBy(fn (StableLedgerRow $r) => $r->service)
            ->map(fn (Collection $rows, string $name) => [
                'name' => $name,
                'count' => $rows->count(),
                'riders' => (int) $rows->sum(fn (StableLedgerRow $r) => $r->riders),
                'collected' => (int) $rows->sum(fn (StableLedgerRow $r) => $r->collected()),
                'stable_share' => (int) $rows->sum(fn (StableLedgerRow $r) => $r->stableShare()),
                'commission' => (int) $rows->sum(fn (StableLedgerRow $r) => $r->commission + $r->vatOnCommission),
            ])
            ->sortByDesc('collected')
            ->take($limit)
            ->values();
    }

    /**
     * Every stable's figures for the period, most sold first (the admins' ranking).
     *
     * @return Collection<int, array{stable: ?Stable, stable_id: int, count: int, collected: int, stable_share: int, our_share: int, commission: int}>
     */
    public function byStable(): Collection
    {
        $grouped = $this->rows()->groupBy(fn (StableLedgerRow $r) => (int) $r->stableId);
        $stables = Stable::query()->whereKey($grouped->keys())->get()->keyBy('id');

        return $grouped
            ->map(fn (Collection $rows, int $id) => [
                'stable' => $stables->get($id),
                'stable_id' => $id,
                'count' => $rows->count(),
                'collected' => (int) $rows->sum(fn (StableLedgerRow $r) => $r->collected()),
                'stable_share' => (int) $rows->sum(fn (StableLedgerRow $r) => $r->stableShare()),
                'our_share' => (int) $rows->sum(fn (StableLedgerRow $r) => $r->ourShare()),
                'commission' => (int) $rows->sum(fn (StableLedgerRow $r) => $r->commission + $r->vatOnCommission),
            ])
            ->sortByDesc('collected')
            ->values();
    }

    /**
     * Customers with a session in the period: first-timers against those who had booked this stable
     * (or, for every stable, any stable) before.
     *
     * @return array{new: int, returning: int}
     */
    public function customers(): array
    {
        $phones = $this->sessionBookings()
            ->whereIn('stable_bookings.status', StableBookingStatus::holdingValues())
            ->join('service_orders', 'service_orders.id', '=', 'stable_bookings.service_order_id')
            ->whereNotNull('service_orders.customer_phone')
            ->distinct()
            ->pluck('service_orders.customer_phone');

        if ($phones->isEmpty()) {
            return ['new' => 0, 'returning' => 0];
        }

        $returning = StableBooking::query()
            ->when($this->stable, fn (Builder $q, Stable $stable) => $q->where('stable_bookings.stable_id', $stable->getKey()))
            ->whereIn('stable_bookings.status', StableBookingStatus::holdingValues())
            ->whereHas('slot', fn (Builder $q) => $q->where('date', '<', $this->from->toDateString()))
            ->join('service_orders', 'service_orders.id', '=', 'stable_bookings.service_order_id')
            ->whereIn('service_orders.customer_phone', $phones)
            ->distinct()
            ->count('service_orders.customer_phone');

        return ['new' => $phones->count() - $returning, 'returning' => $returning];
    }

    /**
     * @return array{cancelled: int, booked: int, cancel_rate: ?int, no_show: int, attended: int, no_show_rate: ?int}
     */
    public function attendance(): array
    {
        $counts = $this->sessionBookings()
            // checkouts that were never paid are not real bookings
            ->where(fn (Builder $q) => $q->where('status', '!=', StableBookingStatus::Cancelled->value)->orWhere('cancellation_source', '!=', 'expired')->orWhereNull('cancellation_source'))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $n = fn (StableBookingStatus $status): int => (int) ($counts[$status->value] ?? 0);
        $all = (int) $counts->sum();
        $cancelled = $n(StableBookingStatus::Cancelled);
        $noShow = $n(StableBookingStatus::NoShow);
        $attended = $n(StableBookingStatus::Completed);

        return [
            'cancelled' => $cancelled,
            'booked' => $all,
            'cancel_rate' => $all > 0 ? (int) round($cancelled * 100 / $all) : null,
            'no_show' => $noShow,
            'attended' => $attended,
            'no_show_rate' => ($noShow + $attended) > 0 ? (int) round($noShow * 100 / ($noShow + $attended)) : null,
        ];
    }

    /** @return array{average: ?float, count: int} reviews written in the period (visible ones) */
    public function rating(): array
    {
        $row = StableReview::query()
            ->visible()
            ->when($this->stable, fn (Builder $q, Stable $stable) => $q->where('stable_id', $stable->getKey()))
            ->whereBetween('created_at', [$this->from, $this->to])
            ->selectRaw('avg(rating) as average, count(*) as total')
            ->first();

        return ['average' => $row && $row->total ? round((float) $row->average, 1) : null, 'count' => (int) ($row->total ?? 0)];
    }

    /** Percentage change against the previous period, or null when there is nothing to compare with. */
    public static function delta(int|float|null $now, int|float|null $before): ?int
    {
        if ($now === null || $before === null || (float) $before === 0.0) {
            return null;
        }

        return (int) round(($now - $before) * 100 / abs($before));
    }
}
