<?php

namespace App\Services\Orders;

use RuntimeException;

/** A refund that cannot be recorded, with a message an admin can read. */
class RefundException extends RuntimeException {}
