<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Api\V1\Owner\Concerns\FindsOwnerStable;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Owner\OwnerClosureResource;
use App\Http\Resources\V1\Owner\OwnerSlotResource;
use App\Models\BookingSlot;
use App\Models\Stable;
use App\Models\StableClosure;
use App\Models\StableOffering;
use App\Services\Stables\SlotGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * The day-to-day side of the stable's times (the /stable panel's Slots and Closed days): close or
 * reopen one time, and close whole days (rain, a sick horse, a holiday). Weekly schedules,
 * services and prices stay on the website's panel.
 */
class OwnerScheduleController extends Controller
{
    use FindsOwnerStable;

    /** How many days the day strip covers, from today. */
    public const DAYS = 14;

    /**
     * My stable's times for a day
     *
     * The times of `date` (default today) by start time, with the closed days from today on;
     * `days` sums up each of the 14 days from today for a day strip.
     */
    public function slots(Request $request, string $slug): JsonResponse
    {
        $stable = $this->ownerStable($request, $slug);
        $today = BookingSlot::today();
        $data = $request->validate([
            /** @example 2026-10-20 */
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $date = $data['date'] ?? $today;
        $last = CarbonImmutable::parse($today)->addDays(self::DAYS - 1)->toDateString();

        $strip = BookingSlot::query()
            ->where('stable_id', $stable->getKey())
            ->whereBetween('date', [$today, $last])
            ->withBookedRiders()
            ->get()
            ->groupBy(fn (BookingSlot $slot) => $slot->date->toDateString());

        $slots = BookingSlot::query()
            ->where('stable_id', $stable->getKey())
            ->where('date', $date)
            ->with(['offering', 'trainer'])
            ->withBookedRiders()
            ->orderBy('start_time')
            ->get();

        return response()->json([
            /** @example 2026-10-20 */
            'date' => $date,
            'days' => collect(range(0, self::DAYS - 1))->map(function (int $i) use ($today, $strip) {
                $day = CarbonImmutable::parse($today)->addDays($i)->toDateString();
                $times = $strip->get($day, collect());

                return [
                    'date' => $day,
                    'times' => $times->count(),
                    'open' => $times->where('is_open', true)->count(),
                    'booked' => (int) $times->sum(fn (BookingSlot $slot) => $slot->bookedRiders()),
                ];
            })->all(),
            'data' => OwnerSlotResource::collection($slots),
            'closures' => OwnerClosureResource::collection($this->upcomingClosures($stable)),
            /** The stable's services, for closing days of one service only. */
            'offerings' => $stable->offerings()->active()->orderBy('id')->get()->map(fn (StableOffering $offering) => [
                'id' => (int) $offering->id,
                'name' => (string) $offering->name,
            ])->all(),
        ]);
    }

    /**
     * Close a time
     *
     * No new bookings; the bookings already made stay (call them off one by one if needed).
     */
    public function close(Request $request, string $slug, int $id): OwnerSlotResource|JsonResponse
    {
        return $this->setOpen($request, $slug, $id, false);
    }

    /** Reopen a time */
    public function open(Request $request, string $slug, int $id): OwnerSlotResource|JsonResponse
    {
        return $this->setOpen($request, $slug, $id, true);
    }

    /** My stable's closed days (from today on) */
    public function closures(Request $request, string $slug): AnonymousResourceCollection
    {
        return OwnerClosureResource::collection($this->upcomingClosures($this->ownerStable($request, $slug)));
    }

    /**
     * Close days
     *
     * The whole stable, or one service (`offering_id`), from `starts_on` to `ends_on`. Their open
     * times are taken down and none are made; times that already have bookings stay but are closed
     * to new ones (`kept`), so call those bookings off if the stable is shut.
     */
    public function storeClosure(Request $request, string $slug, SlotGenerator $generator): JsonResponse
    {
        $stable = $this->ownerStable($request, $slug);
        $today = BookingSlot::today();
        $data = $request->validate([
            /** @example 2026-10-20 */
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            /** @example 2026-10-21 */
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'before_or_equal:'.CarbonImmutable::parse($today)->addYear()->toDateString()],
            'offering_id' => ['nullable', 'integer', Rule::exists('stable_offerings', 'id')->where('stable_id', $stable->getKey())],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        // through the model, so the change log has it (like the panel's Closed days)
        $closure = StableClosure::create([
            'stable_id' => $stable->getKey(),
            'stable_offering_id' => $data['offering_id'] ?? null,
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'reason' => $data['reason'] ?? null,
        ]);

        // the sync already drops the free times on the closed days; applyClosure() closes the booked ones
        $synced = $generator->syncStable($stable);
        $result = $generator->applyClosure($closure);
        $removed = $synced->removed + $result->removed;

        return response()->json([
            'data' => OwnerClosureResource::make($closure->load('offering')),
            /** Open times taken down. */
            'removed' => $removed,
            /** Times with bookings, kept but closed to new bookings. */
            'kept' => $result->kept,
            'message' => trim(__('stable_panel.closures.applied', ['removed' => $removed]).' '.($result->kept > 0 ? __('stable_panel.closures.kept', ['kept' => $result->kept]) : '')),
        ], 201);
    }

    /** Reopen closed days: their times come back from the weekly schedules. */
    public function destroyClosure(Request $request, string $slug, int $id, SlotGenerator $generator): JsonResponse
    {
        $stable = $this->ownerStable($request, $slug);
        $closure = $stable->closures()->whereKey($id)->firstOrFail();

        $closure->delete();
        $generator->syncStable($stable);

        return response()->json(['message' => __('api.owner.reopened')]);
    }

    private function setOpen(Request $request, string $slug, int $id, bool $open): OwnerSlotResource|JsonResponse
    {
        $slot = BookingSlot::query()
            ->where('stable_id', $this->ownerStable($request, $slug)->getKey())
            ->whereKey($id)
            ->with(['offering', 'trainer'])
            ->firstOrFail();

        if ($slot->hasStarted()) {
            return response()->json(['message' => __('api.owner.slot_started'), 'code' => 'slot_started'], 409);
        }

        if ($slot->is_open !== $open) {
            // through the model, so the stable's change log has it
            $slot->update(['is_open' => $open]);
        }

        return OwnerSlotResource::make($slot);
    }

    /** @return Collection<int, StableClosure> */
    private function upcomingClosures(Stable $stable)
    {
        return $stable->closures()
            ->where('ends_on', '>=', BookingSlot::today())
            ->with('offering')
            ->orderBy('starts_on')
            ->get();
    }
}
