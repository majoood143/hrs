<?php

namespace App\Console\Commands;

use App\Enums\StableBookingStatus;
use App\Models\BookingSlot;
use App\Models\StableBooking;
use App\Services\Stables\StableBookingActions;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sessions still confirmed once the attendance window has closed are taken as held (the stable had
 * StableBookingActions::ATTENDANCE_DAYS days to mark a no-show). This bills pay-at-the-stable
 * bookings, which otherwise never reach the statement when nobody marks them.
 */
class CloseStableAttendance extends Command
{
    protected $signature = 'stables:close-attendance';

    protected $description = 'Mark sessions nobody marked within the attendance window as attended';

    public function handle(StableBookingActions $actions): int
    {
        $before = CarbonImmutable::parse(BookingSlot::today())->subDays(StableBookingActions::ATTENDANCE_DAYS)->toDateString();
        $closed = 0;

        StableBooking::query()
            ->where('status', StableBookingStatus::Confirmed->value)
            ->whereHas('slot', fn ($q) => $q->where('date', '<', $before))
            ->each(function (StableBooking $booking) use ($actions, &$closed): void {
                try {
                    $actions->closeAttendance($booking);
                    $closed++;
                } catch (Throwable $e) {
                    report($e);
                }
            });

        $this->info("Closed {$closed} session(s).");

        return self::SUCCESS;
    }
}
