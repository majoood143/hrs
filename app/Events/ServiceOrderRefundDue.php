<?php

namespace App\Events;

use App\Models\ServiceOrder;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A paid order now owes the customer money back, and refunds are made by hand: the admins are told
 * (WhatsApp and the admin panel's notifications, SendRefundDueAlerts).
 */
class ServiceOrderRefundDue
{
    use Dispatchable;

    /** A paid booking was cancelled (by the customer, the stable, or because its time had passed). */
    public const BOOKING_CANCELLED = 'booking_cancelled';

    /** The money came in for an order that had already been cancelled on purpose. */
    public const PAID_AFTER_CANCEL = 'paid_after_cancel';

    public function __construct(public readonly ServiceOrder $order, public readonly string $reason) {}
}
