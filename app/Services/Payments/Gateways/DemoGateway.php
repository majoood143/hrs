<?php

namespace App\Services\Payments\Gateways;

use App\Enums\PaymentGateway;
use App\Models\ServiceOrder;
use App\Services\Payments\Contracts\Gateway;
use App\Services\Payments\PaymentRedirect;

/**
 * A pretend gateway for demos and testing: no merchant account, no money. The customer lands
 * on a page with "Approve" and "Decline" buttons and the whole flow (order, receipt,
 * notifications) runs as it would for a real payment. It is only offered when an admin
 * ticks it under Payment Gateways, and the settings page warns to untick it for live use.
 */
class DemoGateway implements Gateway
{
    public function gateway(): PaymentGateway
    {
        return PaymentGateway::Demo;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function initiate(ServiceOrder $order): PaymentRedirect
    {
        $order->update([
            'payment_session_id' => 'DEMO-'.$order->compactNumber(),
            'payment_method' => PaymentGateway::Demo,
        ]);

        return PaymentRedirect::get(route('payment.demo', ['order' => $order->order_number]));
    }
}
