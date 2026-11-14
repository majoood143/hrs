<?php

namespace App\Services\Reports;

use App\Models\BookClosing;
use App\Models\BookingSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Closed months. Once a month is closed, nothing may be dated inside it any more: a settlement
 * dated there, removing one, or changing an attendance it counted. Payments and refunds are always
 * dated the day they are recorded, so they land in the open month on their own.
 */
class ClosedBooks
{
    /** The last closed day (Y-m-d, Muscat), or null when nothing is closed. */
    public static function through(): ?string
    {
        return BookClosing::query()->latest('id')->value('closed_through');
    }

    /** Whether a day (Y-m-d, Muscat) falls in a closed month. */
    public static function isClosed(?string $date): bool
    {
        $through = static::through();

        return $date !== null && $through !== null && $date <= $through;
    }

    /** The first day still open (for a date picker's minimum), or null when nothing is closed. */
    public static function firstOpenDay(): ?string
    {
        $through = static::through();

        return $through ? CarbonImmutable::parse($through)->addDay()->toDateString() : null;
    }

    /** Close every month up to and including $month (Y-m); it must have ended. */
    public function close(string $month, ?User $by = null, ?string $note = null): BookClosing
    {
        $end = CarbonImmutable::parse($month.'-01')->endOfMonth()->toDateString();

        if ($end >= BookingSlot::today()) {
            throw new InvalidArgumentException(__('closed_books.errors.not_ended'));
        }

        if (static::isClosed($end)) {
            throw new InvalidArgumentException(__('closed_books.errors.already_closed'));
        }

        return BookClosing::create(['closed_through' => $end, 'user_id' => $by?->getKey(), 'note' => $note]);
    }

    /** Reopen the last closed month (the one before it stays closed). */
    public function reopen(?User $by = null, ?string $note = null): BookClosing
    {
        $through = static::through() ?? throw new InvalidArgumentException(__('closed_books.errors.nothing_closed'));
        $before = CarbonImmutable::parse($through)->startOfMonth()->subDay()->toDateString();

        return BookClosing::create(['closed_through' => $before, 'user_id' => $by?->getKey(), 'note' => $note]);
    }
}
