<?php

namespace App\Http\Resources\V1\Owner;

use App\Enums\StableBookingStatus;
use App\Filament\Stable\Resources\StableBookings\StableBookingResource;
use App\Http\Resources\V1\Bookings\SlotResource;
use App\Http\Resources\V1\MoneyResource;
use App\Http\Resources\V1\OptionResource;
use App\Models\BookingSlot;
use App\Models\StableBooking;
use App\Services\Reports\StableLedgerRow;
use App\Support\StableBookingSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A booking as the stable sees it (the /stable panel's Bookings): who comes when, the riders'
 * details, the customer to call, how they pay, the stable's share, and what the stable can do
 * with it now (mark attended / no-show once the day has come, call it off while it is active).
 *
 * @property-read StableBooking $resource
 */
class OwnerBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $booking = $this->resource;
        $booking->loadMissing(['slot.trainer', 'offering', 'order', 'packagePurchase']);
        $order = $booking->order;
        $status = $booking->status;
        $due = $booking->slot && $booking->slot->date->toDateString() <= BookingSlot::today();
        $row = $this->ledgerRow($booking);

        return [
            /** @example BK-7Q2M9XKD */
            'reference' => (string) $booking->reference,
            'status' => OptionResource::of($status->value, $status->label()),
            /** success | warning | danger | gray */
            'tone' => $status->color(),
            'offering' => [
                'id' => (int) $booking->stable_offering_id,
                'name' => (string) $booking->offering?->name,
            ],
            'slot' => SlotResource::make($booking->slot),
            'riders' => (int) $booking->riders,
            /** As entered on the booking form: only the fields the stable asks for are set. */
            'riders_data' => collect($booking->riders_data ?? [])->map(fn (array $rider) => [
                'name' => (string) ($rider['name'] ?? ''),
                /** @var int|null */
                'age' => $rider['age'] ?? null,
                /** @var string|null */
                'level' => isset($rider['level'])
                    ? (in_array($rider['level'], StableBookingSettings::LEVELS, true) ? __('stable_bookings.levels.'.$rider['level']) : (string) $rider['level'])
                    : null,
                /** @var int|string|null */
                'weight' => $rider['weight'] ?? null,
                /** @var int|string|null */
                'height' => $rider['height'] ?? null,
                /** @var string|null */
                'guardian_name' => $rider['guardian_name'] ?? null,
                /** @var string|null */
                'guardian_phone' => $rider['guardian_phone'] ?? null,
                /** @var string|null */
                'notes' => $rider['notes'] ?? null,
            ])->values()->all(),
            'customer' => [
                /** @var string|null */
                'name' => $order?->customer_name,
                /** With the country code, digits only. @var string|null */
                'phone' => $order?->customer_phone,
                /** @var string|null */
                'email' => $order?->customer_email,
            ],
            /** `online`, `at_stable`, `package` or `free`. */
            'payment_option' => (string) $booking->payment_option,
            /** e.g. "Paid online", "Pay at the stable", "Paid with a package". */
            'payment_label' => StableBookingResource::paymentLabel($booking),
            'paid' => (bool) $order?->isPaid(),
            /** What the customer pays in all (fee and VAT included). */
            'total' => new MoneyResource($order?->total),
            /** What the stable earns from it (price + VAT − refunds − our commission); null for free ones. @var MoneyResource|null */
            'stable_share' => $row ? new MoneyResource($row->stableShare() / 1000) : null,
            /** Our commission with its VAT; null for free ones. @var MoneyResource|null */
            'commission' => $row ? new MoneyResource(($row->commission + $row->vatOnCommission) / 1000) : null,
            'refund_due' => (bool) $order?->refundDue(),
            /** `customer`, `stable` or `expired`, once cancelled. @var string|null */
            'cancelled_by' => $booking->cancellation_source,
            /** @var string|null */
            'cancellation_reason' => $booking->cancellation_reason,
            'can_mark_attended' => $due && in_array($status, [StableBookingStatus::Confirmed, StableBookingStatus::NoShow], true),
            'can_mark_no_show' => $due && in_array($status, [StableBookingStatus::Confirmed, StableBookingStatus::Completed], true),
            'can_cancel' => $booking->isActive(),
            /** What cancelling means for this one (a paid booking is refunded); shown before asking. */
            'cancel_hint' => $order?->isPaid() ? __('stable_panel.bookings.cancel_paid_hint') : __('stable_panel.bookings.cancel_hint'),
            /** @var string|null */
            'attended_at' => $booking->attended_at?->toIso8601String(),
            'created_at' => $booking->created_at?->toIso8601String(),
        ];
    }

    private function ledgerRow(StableBooking $booking): ?StableLedgerRow
    {
        $order = $booking->order;

        if (! $order || (float) $order->total <= 0) {
            return null;
        }

        $order->setRelation('stableBooking', $booking);

        return StableLedgerRow::fromOrder($order);
    }
}
