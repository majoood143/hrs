<?php

namespace Tests\Feature;

use App\Models\BookingSlot;
use App\Models\Stable;
use App\Models\StableClosure;
use App\Services\Stables\SlotGenerator;
use Illuminate\Support\Carbon;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/**
 * Weekly schedules into dated slots. "Now" is Sunday 2026-10-04 09:30 in Muscat (05:30 UTC), and the
 * stable opens 14 days ahead: Sundays 4 and 11, Tuesdays 6 and 13.
 */
class StableSlotGeneratorTest extends TestCase
{
    use PreparesStableBookings;

    private Stable $stable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings();
        Carbon::setTestNow('2026-10-04 05:30:00');

        $this->stable = $this->makeStable();
        $this->stable->forceFill(['booking_settings' => ['horizon_days' => 14]])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function generator(): SlotGenerator
    {
        return app(SlotGenerator::class);
    }

    /** @return list<string> "Y-m-d H:i" of the offering's slots */
    private function slotKeys(): array
    {
        return BookingSlot::query()->orderBy('date')->orderBy('start_time')->get()
            ->map(fn (BookingSlot $slot) => $slot->date->toDateString().' '.substr($slot->getRawOriginal('start_time'), 0, 5))
            ->all();
    }

    public function test_it_makes_slots_on_the_chosen_days_in_the_stables_time_zone(): void
    {
        $schedule = $this->makeSchedule($this->makeOffering($this->stable), [0, 2], ['08:00', '10:00']);

        $result = $this->generator()->sync($schedule);

        // 08:00 today already started in Muscat (it is 09:30 there, 05:30 UTC)
        $this->assertSame([
            '2026-10-04 10:00',
            '2026-10-06 08:00', '2026-10-06 10:00',
            '2026-10-11 08:00', '2026-10-11 10:00',
            '2026-10-13 08:00', '2026-10-13 10:00',
        ], $this->slotKeys());
        $this->assertSame(7, $result->created);

        $slot = BookingSlot::query()->where('date', '2026-10-06')->where('start_time', '08:00:00')->sole();
        $this->assertSame('09:00:00', $slot->getRawOriginal('end_time'));
        $this->assertSame(4, $slot->capacity);
        $this->assertSame('2026-10-06 04:00:00', $slot->startsAt()->utc()->format('Y-m-d H:i:s'));

        // running again changes nothing
        $again = $this->generator()->sync($schedule);
        $this->assertSame([0, 0, 0, 0], [$again->created, $again->updated, $again->removed, $again->kept]);
    }

    public function test_the_schedule_dates_and_capacity_override_are_respected(): void
    {
        $schedule = $this->makeSchedule($this->makeOffering($this->stable), [0, 2], ['16:00'], [
            'valid_from' => '2026-10-06',
            'valid_until' => '2026-10-11',
            'capacity' => 2,
        ]);

        $this->generator()->sync($schedule);

        $this->assertSame(['2026-10-06 16:00', '2026-10-11 16:00'], $this->slotKeys());
        $this->assertSame([2, 2], BookingSlot::query()->pluck('capacity')->all());
    }

    public function test_closed_days_get_no_slots_and_inactive_schedules_or_offerings_none_at_all(): void
    {
        $offering = $this->makeOffering($this->stable);
        StableClosure::create(['stable_id' => $this->stable->id, 'starts_on' => '2026-10-06', 'ends_on' => '2026-10-11']);
        $schedule = $this->makeSchedule($offering, [0, 2], ['10:00']);

        $this->generator()->sync($schedule);
        $this->assertSame(['2026-10-04 10:00', '2026-10-13 10:00'], $this->slotKeys());

        $offering->update(['is_active' => false]);
        $result = $this->generator()->sync($schedule->refresh());

        $this->assertSame(2, $result->removed);
        $this->assertSame([], $this->slotKeys());
    }

    public function test_places_are_counted_from_bookings_that_hold_them(): void
    {
        $this->generator()->sync($this->makeSchedule($this->makeOffering($this->stable), [2], ['10:00']));
        $slot = BookingSlot::query()->where('date', '2026-10-06')->sole();

        $this->book($slot, 2, 'confirmed');
        $this->book($slot, 1, 'pending');
        $this->book($slot, 1, 'cancelled');

        $this->assertSame(3, $slot->bookedRiders());
        $this->assertSame(1, $slot->remainingPlaces());
        $this->assertSame(3, (int) BookingSlot::query()->withBookedRiders()->find($slot->id)->booked_riders);
    }

    public function test_saving_a_schedule_rewrites_empty_slots_but_never_booked_ones(): void
    {
        $offering = $this->makeOffering($this->stable);
        $schedule = $this->makeSchedule($offering, [2], ['10:00']);
        $this->generator()->sync($schedule);

        $booked = BookingSlot::query()->where('date', '2026-10-06')->sole();
        $this->book($booked, 1);

        $offering->update(['duration_minutes' => 90]);
        $schedule->update(['capacity' => 6]);
        $result = $this->generator()->sync($schedule->refresh(), refresh: true);

        $this->assertSame(1, $result->updated);
        $empty = BookingSlot::query()->where('date', '2026-10-13')->sole();
        $this->assertSame([6, '11:30:00'], [$empty->capacity, $empty->getRawOriginal('end_time')]);
        $this->assertSame([4, '11:00:00'], [$booked->refresh()->capacity, $booked->getRawOriginal('end_time')]);
    }

    public function test_the_nightly_run_keeps_changes_made_to_a_single_slot(): void
    {
        $schedule = $this->makeSchedule($this->makeOffering($this->stable), [2], ['10:00']);
        $this->generator()->sync($schedule);

        $slot = BookingSlot::query()->where('date', '2026-10-13')->sole();
        $slot->update(['capacity' => 1, 'is_open' => false]);

        $this->generator()->syncStable($this->stable);

        $this->assertSame([1, false], [$slot->refresh()->capacity, $slot->is_open]);
    }

    public function test_dropping_a_day_removes_its_empty_slots_and_keeps_booked_ones(): void
    {
        $schedule = $this->makeSchedule($this->makeOffering($this->stable), [0, 2], ['10:00']);
        $this->generator()->sync($schedule);
        $this->book(BookingSlot::query()->where('date', '2026-10-06')->sole(), 1);

        $schedule->update(['weekdays' => [0]]);
        $result = $this->generator()->sync($schedule->refresh(), refresh: true);

        $this->assertSame([1, 1], [$result->removed, $result->kept]);
        $this->assertSame(['2026-10-04 10:00', '2026-10-06 10:00', '2026-10-11 10:00'], $this->slotKeys());
    }

    public function test_a_new_closure_removes_empty_slots_and_closes_booked_ones(): void
    {
        $this->generator()->sync($this->makeSchedule($this->makeOffering($this->stable), [0, 2], ['10:00']));
        $booked = BookingSlot::query()->where('date', '2026-10-11')->sole();
        $this->book($booked, 1);

        $closure = StableClosure::create(['stable_id' => $this->stable->id, 'starts_on' => '2026-10-06', 'ends_on' => '2026-10-11']);
        $result = $this->generator()->applyClosure($closure);

        $this->assertSame([1, 1], [$result->removed, $result->kept]);
        $this->assertFalse($booked->refresh()->is_open);
        $this->assertSame(['2026-10-04 10:00', '2026-10-11 10:00', '2026-10-13 10:00'], $this->slotKeys());

        // lifting it brings the empty day back
        $closure->delete();
        $this->generator()->syncStable($this->stable);
        $this->assertContains('2026-10-06 10:00', $this->slotKeys());
    }

    public function test_deleting_a_schedule_removes_its_future_empty_slots_only(): void
    {
        $schedule = $this->makeSchedule($this->makeOffering($this->stable), [2], ['10:00']);
        $this->generator()->sync($schedule);
        $booked = BookingSlot::query()->where('date', '2026-10-06')->sole();
        $this->book($booked, 1);

        $schedule->delete();

        $this->assertSame(['2026-10-06 10:00'], $this->slotKeys());
        $this->assertNull($booked->refresh()->stable_schedule_id);
    }

    public function test_the_command_fills_every_stables_window(): void
    {
        $this->makeSchedule($this->makeOffering($this->stable), [2], ['10:00']);

        $this->artisan('stables:generate-slots')->assertSuccessful();

        $this->assertSame(['2026-10-06 10:00', '2026-10-13 10:00'], $this->slotKeys());
    }
}
