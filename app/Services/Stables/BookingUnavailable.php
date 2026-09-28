<?php

namespace App\Services\Stables;

use RuntimeException;

/** A booking that cannot be made (or changed) as asked; the message is for the customer, translated. */
class BookingUnavailable extends RuntimeException {}
