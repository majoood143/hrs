<?php

namespace App\Services\Stables;

use App\Models\BookingSlot;
use App\Models\Stable;
use App\Models\StableClosure;
use App\Models\StableSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns weekly schedules into dated booking slots, from today up to the stable's horizon, skipping
 * closed days and times that have already started.
 *
 * The rules it never breaks:
 * - a slot that has already started is history and is never touched;
 * - a slot with bookings is never deleted or changed by a sync, only reported as "kept";
 * - an existing slot is only rewritten from its schedule when the owner saves that schedule
 *   ($refresh), so the nightly run never undoes a change made to a single slot.
 */
class SlotGenerator
{
    public function sync(StableSchedule $schedule, bool $refresh = false): SlotSyncResult
    {
        $schedule->loadMissing(['stable', 'offering']);

        // the change log records what a person did (the schedule they saved), not the hundreds of
        // slots that follow from it
        return activity()->withoutLogs(fn () => DB::transaction(function () use ($schedule, $refresh): SlotSyncResult {
            $result = new SlotSyncResult;
            $wanted = $this->wantedStarts($schedule);
            $offering = $schedule->offering;
            $capacity = $schedule->capacity ?: $offering->capacity;

            $existing = BookingSlot::query()
                ->where('stable_offering_id', $offering->getKey())
                ->where('date', '>=', BookingSlot::today())
                ->withBookedRiders()
                ->get()
                ->filter(fn (BookingSlot $slot) => ! $slot->hasStarted())
                ->keyBy(fn (BookingSlot $slot) => $slot->date->toDateString().' '.$slot->getRawOriginal('start_time'));

            foreach ($wanted as $key => $start) {
                /** @var BookingSlot|null $slot */
                $slot = $existing->get($key);
                $end = $start->addMinutes($offering->duration_minutes)->format('H:i:s');

                if ($slot === null) {
                    BookingSlot::create([
                        'stable_id' => $schedule->stable_id,
                        'stable_offering_id' => $offering->getKey(),
                        'stable_schedule_id' => $schedule->getKey(),
                        'date' => $start->toDateString(),
                        'start_time' => $start->format('H:i:s'),
                        'end_time' => $end,
                        'capacity' => $capacity,
                        'trainer_id' => $schedule->trainer_id,
                    ]);
                    $result->created++;

                    continue;
                }

                if ($refresh && (int) $slot->stable_schedule_id === (int) $schedule->getKey() && ! $slot->hasBookings()) {
                    $slot->fill(['end_time' => $end, 'capacity' => $capacity, 'trainer_id' => $schedule->trainer_id]);

                    if ($slot->isDirty()) {
                        $slot->save();
                        $result->updated++;
                    }
                }
            }

            $existing
                ->filter(fn (BookingSlot $slot) => (int) $slot->stable_schedule_id === (int) $schedule->getKey())
                ->reject(fn (BookingSlot $slot, string $key) => $wanted->has($key))
                ->each(function (BookingSlot $slot) use ($result): void {
                    if ($slot->hasBookings()) {
                        $result->kept++;

                        return;
                    }

                    $slot->delete();
                    $result->removed++;
                });

            return $result;
        }));
    }

    /** Every schedule of a stable; the nightly run passes $refresh = false. */
    public function syncStable(Stable $stable, bool $refresh = false): SlotSyncResult
    {
        return $stable->schedules()->with(['stable', 'offering'])->get()
            ->reduce(fn (SlotSyncResult $carry, StableSchedule $schedule) => $carry->add($this->sync($schedule, $refresh)), new SlotSyncResult);
    }

    /**
     * A new closure: its future slots without bookings go (they come back if the closure is
     * removed); the ones with bookings are closed to new bookings and reported as kept, so the
     * owner can deal with those customers.
     */
    public function applyClosure(StableClosure $closure): SlotSyncResult
    {
        $result = new SlotSyncResult;

        $this->closureSlots($closure)->each(function (BookingSlot $slot) use ($result): void {
            if ($slot->hasBookings()) {
                $slot->forceFill(['is_open' => false])->save();
                $result->kept++;

                return;
            }

            $slot->delete();
            $result->removed++;
        });

        return $result;
    }

    /** @return Collection<int, BookingSlot> future slots inside a closure */
    public function closureSlots(StableClosure $closure): Collection
    {
        return BookingSlot::query()
            ->where('stable_id', $closure->stable_id)
            ->when($closure->stable_offering_id, fn ($q, $id) => $q->where('stable_offering_id', $id))
            ->whereBetween('date', [max($closure->starts_on->toDateString(), BookingSlot::today()), $closure->ends_on->toDateString()])
            ->withBookedRiders()
            ->get()
            ->reject(fn (BookingSlot $slot) => $slot->hasStarted())
            ->values();
    }

    /** @return Collection<string, CarbonImmutable> "Y-m-d H:i:s" => start, in the stable's time zone */
    private function wantedStarts(StableSchedule $schedule): Collection
    {
        $offering = $schedule->offering;
        $stable = $schedule->stable;

        if (! $schedule->is_active || ! $offering?->is_active || $stable === null) {
            return collect();
        }

        $tz = BookingSlot::timezone();
        $now = CarbonImmutable::now($tz);
        $today = $now->startOfDay();
        $from = $schedule->valid_from ? CarbonImmutable::parse($schedule->valid_from->toDateString(), $tz) : $today;
        $from = $from->max($today);
        $to = $today->addDays($stable->bookingSettings()->horizonDays() - 1);

        if ($schedule->valid_until) {
            $to = $to->min(CarbonImmutable::parse($schedule->valid_until->toDateString(), $tz));
        }

        if ($from->greaterThan($to)) {
            return collect();
        }

        $weekdays = $schedule->weekdayNumbers();
        $times = $schedule->startTimes();
        $closures = StableClosure::query()
            ->where('stable_id', $stable->getKey())
            ->where(fn ($q) => $q->whereNull('stable_offering_id')->orWhere('stable_offering_id', $offering->getKey()))
            ->where('starts_on', '<=', $to->toDateString())
            ->where('ends_on', '>=', $from->toDateString())
            ->get();

        $wanted = collect();

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date = $day->format('Y-m-d');

            if (! in_array((int) $day->dayOfWeek, $weekdays, true)) {
                continue;
            }

            if ($closures->contains(fn (StableClosure $c) => $c->starts_on->toDateString() <= $date && $c->ends_on->toDateString() >= $date)) {
                continue;
            }

            foreach ($times as $time) {
                $start = CarbonImmutable::parse($date.' '.$time, $tz);

                if ($start->greaterThan($now)) {
                    $wanted->put($date.' '.$time, $start);
                }
            }
        }

        return $wanted;
    }
}
