<?php

namespace App\Console\Commands;

use App\Enums\StableBookingStatus;
use App\Jobs\SendStableBookingNotification;
use App\Models\BookingSlot;
use App\Models\StableBooking;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Reminds customers of tomorrow's session by SMS: confirmed bookings starting within the next day
 * (and more than two hours away), once each, at stables that have reminders on. A booking whose
 * confirmation went out less than 12 hours ago waits for a later run, so the customer does not get
 * a reminder right on top of the confirmation.
 */
class SendStableBookingReminders extends Command
{
    protected $signature = 'stables:send-reminders';

    protected $description = 'SMS customers a reminder of their stable session the day before';

    public const AHEAD_HOURS = 24;

    public const NOT_WITHIN_HOURS = 2;

    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $today = BookingSlot::today();
        $sent = 0;

        StableBooking::query()
            ->where('status', StableBookingStatus::Confirmed->value)
            ->whereNull('reminded_at')
            ->whereHas('slot', fn ($q) => $q->whereBetween('date', [$today, CarbonImmutable::parse($today)->addDays(2)->toDateString()]))
            ->with(['slot', 'stable', 'order'])
            ->each(function (StableBooking $booking) use ($now, &$sent): void {
                $start = $booking->slot?->startsAt();

                if (! $start || ! $booking->order?->customer_phone || ! ($booking->stable?->bookingSettings()->sendsReminders() ?? false)) {
                    return;
                }

                $due = $start->lessThanOrEqualTo($now->addHours(self::AHEAD_HOURS)) && $start->greaterThan($now->addHours(self::NOT_WITHIN_HOURS));
                $confirmedAWhileAgo = ($booking->confirmed_at ?? $booking->created_at)->lessThanOrEqualTo($now->subHours(12));

                if (! $due || ! $confirmedAWhileAgo) {
                    return;
                }

                $booking->forceFill(['reminded_at' => now()])->saveQuietly();
                SendStableBookingNotification::dispatch($booking->getKey(), 'customer', 'sms', SendStableBookingNotification::REMINDER);
                $sent++;
            });

        $this->info("Reminded {$sent} booking(s).");

        return self::SUCCESS;
    }
}
