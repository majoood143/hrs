<?php

namespace App\Services\Notifications;

use App\Jobs\SendOrderNotification;
use App\Models\ServiceOrder;
use App\Support\FormOrderSettings;

/**
 * Tells the customer about their order, by email and by SMS as the order's form allows. The
 * sending itself happens in queued jobs (SMS and mail can be slow), one per channel, so a failing
 * channel never holds up the other or the payment that triggered it.
 */
class OrderNotifier
{
    public const RECEIVED = 'order_received';

    public const COMPLETED = 'order_completed';

    public const REJECTED = 'order_rejected';

    public const REFUNDED = 'order_refunded';

    /** @return list<string> the channels a message was queued on */
    public function notify(ServiceOrder $order, string $type, bool $force = false): array
    {
        $settings = $order->form ? FormOrderSettings::for($order->form) : null;

        // an order whose form is gone still gets its email, but no SMS unless a form asked for it
        $channels = array_keys(array_filter([
            'email' => $settings?->notifiesByEmail() ?? true,
            'sms' => $settings?->notifiesBySms() ?? false,
        ]));

        foreach ($channels as $channel) {
            SendOrderNotification::dispatch($order->getKey(), $channel, $type, $force);
        }

        return $channels;
    }
}
