<?php

namespace App\Services\Notifications;

use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use App\Support\NotificationText;

/** The SMS texts. They are short on purpose: one message, and the link carries the detail. */
class OrderMessages
{
    /** Call inside the order's language (the job sets it). */
    public function sms(string $type, ServiceOrder $order): string
    {
        $data = [
            'site' => SiteSetting::siteName(),
            'number' => $order->order_number,
            'service' => $order->service?->localizedName() ?? __('orders.service'),
            'url' => route('orders.show', $order->order_number),
        ];

        $refund = $order->refunds()->reorder()->latest('id')->first();
        $data['amount'] = $order->currency.' '.number_format((float) ($refund?->amount ?? 0), 3);

        return match (true) {
            $type === OrderNotifier::COMPLETED => NotificationText::get('sms.completed', $data),
            $type === OrderNotifier::REJECTED => NotificationText::get('sms.rejected', $data),
            $type === OrderNotifier::REFUNDED => NotificationText::get('sms.refunded', $data),
            $order->isPaid() => NotificationText::get('sms.received_paid', $data),
            default => NotificationText::get('sms.received_free', $data),
        };
    }
}
