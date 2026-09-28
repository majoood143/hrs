<?php

namespace App\Services\Payments\Contracts;

use App\Enums\PaymentGateway;
use App\Models\ServiceOrder;
use App\Models\StablePaymentAccount;
use App\Services\Payments\PaymentRedirect;
use RuntimeException;

interface Gateway
{
    public function gateway(): PaymentGateway;

    /** Whether the credentials this gateway needs are filled in. */
    public function isConfigured(): bool;

    /**
     * Start a payment for the order and say where to send the customer. The order keeps the
     * gateway's session / payment id so the callback can find it again.
     *
     * @throws RuntimeException when the gateway refuses or cannot be reached
     */
    public function initiate(ServiceOrder $order): PaymentRedirect;

    /** A copy that uses a stable's own account (null: the site's keys, as set in Payment Gateways). */
    public function forAccount(?StablePaymentAccount $account): static;

    public function account(): ?StablePaymentAccount;
}
