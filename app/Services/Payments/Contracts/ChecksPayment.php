<?php

namespace App\Services\Payments\Contracts;

use App\Models\ServiceOrder;
use RuntimeException;

/** A gateway we can ask about a payment, so nothing has to wait for the customer's browser to come back. */
interface ChecksPayment
{
    /**
     * Ask the gateway and settle the order against its answer. Safe to call again.
     *
     * @return string|null the gateway's answer ('paid' when it was), or null when there is nothing to check
     *
     * @throws RuntimeException
     */
    public function reconcile(ServiceOrder $order, ?string $sessionId = null): ?string;
}
