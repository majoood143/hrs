<?php

namespace App\Http\Resources\V1\Owner;

use App\Enums\PaymentStatus;
use App\Http\Resources\V1\MoneyResource;
use App\Http\Resources\V1\OptionResource;
use App\Models\BookingSlot;
use App\Models\PosPayment;
use App\Models\ServiceOrder;
use App\Services\Reports\PosReport;
use App\Services\Stables\BookingTicketPdf;
use App\Support\StablePos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One sale made at a stable's desk (the POS): a session or a lesson package, how it was paid, and
 * what can still be done with it (refund, send the payment link again, the receipt and ticket).
 *
 * @property-read ServiceOrder $resource
 */
class PosSaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order = $this->resource;
        $booking = $order->stableBooking;
        $purchase = $order->packagePurchase;
        $status = $this->status($order);
        $locale = app()->getLocale();

        return [
            /** @example SO-2026-000123 */
            'number' => (string) $order->order_number,
            /** session | package */
            'kind' => $purchase ? 'package' : 'session',
            /** The booking's (BK-…) or the package's (PK-…) reference. @var string|null */
            'reference' => $booking?->reference ?? $purchase?->reference,
            'item' => PosReport::itemName($order),
            /** The session's day and time, for a session. @var array{date: string, start_time: string}|null */
            'session' => $booking?->slot ? [
                'date' => $booking->slot->date->toDateString(),
                'start_time' => $booking->slot->startsAt()->format('H:i'),
            ] : null,
            'riders' => (int) ($booking?->riders ?? 0),
            'customer' => [
                'name' => $order->customerName(),
                /** @var string|null */
                'phone' => $order->customer_phone,
            ],
            'total' => new MoneyResource($order->total),
            /** The amount of a promo code taken off, if any. @var MoneyResource|null */
            'discount' => (float) $order->discount_amount > 0 ? new MoneyResource($order->discount_amount) : null,
            'refunded' => new MoneyResource($order->refunded_amount),
            /** paid | refunded | partly_refunded | awaiting_payment | cancelled | free */
            'status' => OptionResource::of($status, __('stable_pos.statuses.'.$status)),
            /** success | warning | danger | gray */
            'status_tone' => match ($status) {
                'paid', 'free' => 'success',
                'awaiting_payment', 'partly_refunded' => 'warning',
                'refunded' => 'danger',
                default => 'gray',
            },
            /** Sent to the customer as a payment link instead of being paid at the desk. */
            'via_link' => ! $order->isDeskSale() && ! $order->isFree(),
            'payments' => $order->posPayments->map(fn (PosPayment $p) => [
                'method' => OptionResource::of($p->method, StablePos::label($p->method)),
                'amount' => new MoneyResource($p->amount),
                /** @var string|null */
                'reference' => $p->reference,
            ])->all(),
            /** @var string|null */
            'sold_by' => $order->deskSeller?->name,
            /** @example 2026-11-30T10:15:00+04:00 */
            'sold_at' => $order->created_at->setTimezone(BookingSlot::timezone())->toIso8601String(),
            /** The payment link while it waits for payment (share it with the customer). @var string|null */
            'pay_url' => $order->isPayable() ? route('payment.start', $order->order_number) : null,
            /** @var string|null */
            'receipt_url' => $booking ? app(BookingTicketPdf::class)->receiptUrl($booking, $locale) : null,
            /** @var string|null */
            'ticket_url' => $booking ? app(BookingTicketPdf::class)->url($booking) : null,
            /** Paid at the desk with money left to give back. */
            'can_refund' => $order->isDeskSale() && $order->isPaid() && $order->refundableAmount() > 0,
        ];
    }

    private function status(ServiceOrder $order): string
    {
        return match (true) {
            $order->payment_status === PaymentStatus::Free => 'free',
            $order->payment_status === PaymentStatus::Refunded => 'refunded',
            $order->isPaid() && (float) $order->refunded_amount > 0 => 'partly_refunded',
            $order->isPaid() => 'paid',
            $order->isPayable() => 'awaiting_payment',
            default => 'cancelled',
        };
    }
}
