<?php

namespace App\Events;

use App\Models\StableBooking;
use Illuminate\Foundation\Events\Dispatchable;

/** The customer or the stable cancelled a confirmed booking: both are told (not sent for unpaid checkouts that expire). */
class StableBookingCancelled
{
    use Dispatchable;

    public function __construct(public readonly StableBooking $booking) {}
}
