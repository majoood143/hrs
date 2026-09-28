<?php

namespace App\Services\Stables;

use App\Filament\Stable\Resources\StableBookings\StableBookingResource;
use App\Models\SiteSetting;
use App\Models\StableBooking;
use Throwable;

/**
 * The texts about a booking, in the current language (callers switch to the reader's): the facts
 * both sides need (what, where, when, how many) and, for a cancellation, who called it off and
 * what happens to the money.
 */
class StableBookingMessages
{
    /** @return array<string, string> the placeholders every text can use */
    public function data(StableBooking $booking): array
    {
        $booking->loadMissing(['slot', 'offering', 'stable', 'order']);
        $start = $booking->slot?->startsAt()->locale(app()->getLocale());
        $order = $booking->order;

        return [
            'site' => SiteSetting::siteName(),
            'reference' => $booking->reference,
            'offering' => (string) $booking->offering?->name,
            'stable' => (string) $booking->stable?->name,
            'date' => $start ? $start->translatedFormat('l j F Y') : '',
            'time' => (string) $booking->slot?->timeRange(),
            'riders' => trans_choice('stable_bookings.riders_count', $booking->riders, ['count' => $booking->riders]),
            'names' => $booking->riderNames(),
            'customer' => (string) ($order?->customer_name ?: __('orders.guest')),
            'phone' => (string) $order?->customer_phone,
            'payment' => $this->paymentLine($booking),
            'total' => $order ? SiteSetting::formatCurrency((float) $order->total, 3) : '',
            'url' => $order ? route('orders.show', $order->order_number) : url('/'),
            'panel' => $this->panelUrl($booking),
            'reason' => (string) $booking->cancellation_reason,
            'map' => (string) $booking->stable?->map_link,
        ];
    }

    public function customerSms(string $type, StableBooking $booking): string
    {
        $data = $this->data($booking);

        if ($type === 'reminder') {
            return __(filled($data['map']) ? 'stable_bookings.sms.reminder_map' : 'stable_bookings.sms.reminder', $data);
        }

        if ($type === 'review_invite') {
            return __('stable_reviews.sms.invite', $data + ['review' => $this->reviewUrl($booking)]);
        }

        return $type === 'cancelled'
            ? __('stable_bookings.sms.customer_cancelled_'.($booking->cancellation_source === 'stable' ? 'stable' : 'customer'), $data)
            : __('stable_bookings.sms.customer_confirmed', $data);
    }

    /** The stable's alert: SMS, the WhatsApp body, and the email's summary line. */
    public function stableText(string $type, StableBooking $booking): string
    {
        $data = $this->data($booking);

        if ($type === 'cancelled') {
            return __('stable_bookings.alert.cancelled', $data + [
                'who' => __('stable_bookings.cancelled_by.'.($booking->cancellation_source ?: 'customer')),
            ]).($this->refundDue($booking) ? ' '.__('stable_bookings.alert.refund_note') : '');
        }

        return __('stable_bookings.alert.confirmed', $data);
    }

    public function refundDue(StableBooking $booking): bool
    {
        return (bool) $booking->order?->refundDue();
    }

    private function paymentLine(StableBooking $booking): string
    {
        return match ($booking->payment_option) {
            'at_stable' => __('stable_bookings.payment.at_stable', ['total' => SiteSetting::formatCurrency((float) $booking->order?->total, 3)]),
            'free' => __('stable_bookings.payment.free'),
            'package' => __('stable_bookings.payment.package', ['reference' => (string) $booking->packagePurchase?->reference]),
            default => $booking->order?->isPaid()
                ? __('stable_bookings.payment.paid_online')
                : __('stable_bookings.payment.pending'),
        };
    }

    /** The customer's own order page, where the review form is (sign-in with the phone first). */
    public function reviewUrl(StableBooking $booking): string
    {
        return $booking->order ? route('account.orders.show', $booking->order->order_number).'#review' : url('/');
    }

    private function panelUrl(StableBooking $booking): string
    {
        try {
            return StableBookingResource::getUrl('index', panel: 'stable', tenant: $booking->stable);
        } catch (Throwable) {
            return url('/stable');
        }
    }
}
