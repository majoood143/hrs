<?php

namespace App\Services\WhatsApp;

use App\Filament\Resources\ServiceOrderResource;
use App\Models\ServiceOrder;
use App\Models\SiteSetting;

/** The admins' alert for a paid order that now owes the customer money back: who, how much, why, and the admin link. */
final class RefundDueAlert
{
    public const TYPE = 'refund_due';

    public static function message(ServiceOrder $order, string $reason): string
    {
        return BilingualMessage::make(fn () => implode("\n", self::lines($order, $reason)));
    }

    /**
     * The alert's lines in the current language (the admin panel's notification uses them too).
     *
     * @return list<string>
     */
    public static function lines(ServiceOrder $order, string $reason): array
    {
        $booking = $order->stableBooking()->with('stable')->first();

        return array_values(array_filter([
            __('whatsapp.refund_due.heading', ['site' => SiteSetting::siteName()]),
            '',
            __('whatsapp.refund_due.reason.'.$reason),
            __('whatsapp.refund_due.order', ['number' => $order->order_number]),
            $booking ? __('whatsapp.refund_due.booking', ['reference' => $booking->reference, 'stable' => $booking->stable?->name]) : null,
            __('whatsapp.refund_due.customer', ['name' => $order->customer_name ?: '—', 'phone' => $order->customer_phone ?: '—']),
            __('whatsapp.refund_due.amount', ['amount' => SiteSetting::formatCurrency($order->refundableAmount())]),
            // the stable's own payment account took the money: the refund is made from there
            $order->collected_by === 'stable' ? __('whatsapp.refund_due.stable_account') : null,
            '',
            __('whatsapp.refund_due.admin', ['link' => self::adminUrl($order)]),
        ], fn ($line) => $line !== null));
    }

    public static function adminUrl(ServiceOrder $order): string
    {
        return ServiceOrderResource::getUrl('view', ['record' => $order], panel: 'admin');
    }
}
