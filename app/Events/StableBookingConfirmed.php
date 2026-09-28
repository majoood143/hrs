<?php

namespace App\Events;

use App\Models\StableBooking;
use Illuminate\Foundation\Events\Dispatchable;

/** A booking is confirmed (paid, free, or to be paid at the stable): the customer and the stable are told. */
class StableBookingConfirmed
{
    use Dispatchable;

    public function __construct(public readonly StableBooking $booking) {}
}
