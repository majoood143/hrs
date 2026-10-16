<?php

namespace App\Http\Resources\V1\Bookings;

use App\Enums\StableBookingStatus;
use App\Http\Resources\V1\MoneyResource;
use App\Http\Resources\V1\OptionResource;
use App\Models\Customer;
use App\Models\SiteSetting;
use App\Models\StableBooking;
use App\Services\Stables\StableBookingActions;
use App\Services\Stables\StableReviews;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A signed-in customer's booking: the session, the riders, the money and what they can still do
 * (pay, cancel, review). What the website's booking card on the account order page shows.
 *
 * @property-read StableBooking $resource
 */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $booking = $this->resource;
        $booking->loadMissing(['slot', 'offering', 'stable.region', 'stable.city', 'order', 'review', 'packagePurchase.package']);
        $order = $booking->order;
        $slot = $booking->slot;
        $customer = $request->user('customer_api');
        $hours = $booking->stable?->bookingSettings()->cancellationHours();
        $total = $order?->total;

        return [
            /** @example BK-7Q2M9XKD */
            'reference' => (string) $booking->reference,
            'status' => OptionResource::of($booking->status->value, $booking->status->label()),
            /** success | warning | danger | gray */
            'tone' => $booking->status->color(),
            'stable' => BookingStableResource::make($booking->stable),
            'offering' => OfferingResource::make($booking->offering),
            'slot' => SlotResource::make($slot),
            'riders' => (int) $booking->riders,
            /** The riders as they were entered (name, and the fields the stable asks for). */
            'riders_data' => collect($booking->riders_data ?? [])->map(fn (array $rider) => [
                'name' => (string) ($rider['name'] ?? ''),
                /** @var int|null */
                'age' => $rider['age'] ?? null,
                /** @var string|null */
                'level' => isset($rider['level']) ? __('stable_bookings.levels.'.$rider['level']) : null,
            ])->values()->all(),
            /** `online`, `at_stable`, `package` or `free`. */
            'payment_option' => $booking->payment_option,
            /** One line about the money, e.g. "Paid online" or "To pay at the stable: OMR 31.500". */
            'payment_summary' => $this->paymentSummary($booking),
            'order' => $order ? [
                /** @example SO-2026-000123 */
                'number' => (string) $order->order_number,
                'status' => OptionResource::of($order->status->value, $order->status->label()),
                'payment_status' => OptionResource::of($order->payment_status->value, $order->payment_status->label()),
                'price' => new MoneyResource($order->price),
                'fee' => new MoneyResource($order->fee_amount),
                'vat' => new MoneyResource((float) $order->vat_on_price + (float) $order->vat_on_fee),
                'total' => new MoneyResource($total),
                'refund_due' => $order->refundDue(),
                /** @var string|null */
                'paid_at' => $order->paid_at?->toIso8601String(),
            ] : null,
            /** The website's checkout, while the booking waits for payment; open it in a browser. */
            'pay_url' => $order?->isPayable() ? route('payment.start', $order->order_number) : null,
            'can_cancel' => app(StableBookingActions::class)->customerMayCancel($booking),
            /** The stable's rule, while the booking is confirmed; null otherwise. */
            'cancellation_policy' => $booking->status === StableBookingStatus::Confirmed
                ? ($hours === null ? __('stable_bookings.policy.no_online_cancel') : __('stable_bookings.policy.cancel_until', ['hours' => $hours]))
                : null,
            /** `customer`, `stable` or `expired` (an unpaid checkout), once cancelled. */
            'cancelled_by' => $booking->cancellation_source,
            /** The stable's reason, when it cancelled. */
            'cancellation_reason' => $booking->cancellation_source === 'stable' ? $booking->cancellation_reason : null,
            'can_review' => $customer instanceof Customer && app(StableReviews::class)->canReview($booking, $customer),
            'review' => $booking->review ? [
                'rating' => (int) $booking->review->rating,
                /** @var string|null */
                'comment' => $booking->review->comment,
                /** @var string|null */
                'reply' => $booking->review->reply,
            ] : null,
            'created_at' => $booking->created_at?->toIso8601String(),
            /** The order's page on the website (receipt, timeline). */
            'order_url' => $order ? route('orders.show', $order->order_number) : null,
        ];
    }

    private function paymentSummary(StableBooking $booking): string
    {
        $order = $booking->order;
        $currency = SiteSetting::currency();
        $total = trim(($currency['code'] ?? '').' '.number_format((float) $order?->total, 3));

        return match (true) {
            $booking->payment_option === 'package' => __('stable_bookings.payment.package', ['reference' => (string) $booking->packagePurchase?->reference]),
            $booking->payment_option === 'free' || $order?->payment_status?->value === 'free' => __('stable_bookings.payment.free'),
            $booking->payment_option === 'at_stable' => __('stable_bookings.payment.at_stable', ['total' => $total]),
            (bool) $order?->isPaid() => __('stable_bookings.payment.paid_online'),
            default => __('stable_bookings.payment.pending'),
        };
    }
}
