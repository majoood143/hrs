<?php

namespace App\Http\Resources\V1\Bookings;

use App\Http\Resources\V1\MoneyResource;
use App\Http\Resources\V1\OptionResource;
use App\Models\StablePackagePurchase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A package the signed-in customer bought (or is paying for).
 *
 * @property-read StablePackagePurchase $resource
 */
class PackagePurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $purchase = $this->resource;
        $order = $purchase->order;
        $status = $purchase->statusKey();

        return [
            'id' => (int) $purchase->id,
            /** @example PK-7Q2M9XKD */
            'reference' => (string) $purchase->reference,
            'name' => (string) ($purchase->package?->name ?? $purchase->name),
            /** `pending` (awaiting payment), `active`, `expired` or `cancelled`. */
            'status' => OptionResource::of($status, __('stable_packages.status.'.$status)),
            /** success | warning | danger | gray */
            'tone' => match ($status) {
                'active' => 'success',
                'pending' => 'warning',
                'cancelled' => 'danger',
                default => 'gray',
            },
            'sessions' => (int) $purchase->sessions,
            'sessions_left' => $purchase->sessionsLeft(),
            /** @var string|null */
            'expires_at' => $purchase->expires_at?->toIso8601String(),
            'offering_id' => (int) $purchase->stable_offering_id,
            /** @var string|null */
            'offering_name' => $purchase->offering?->name,
            'stable' => BookingStableResource::make($purchase->stable),
            'total' => new MoneyResource($order?->total),
            /** @example SO-2026-000123 */
            'order_number' => $order?->order_number,
            /** The website's checkout, while it waits for payment; open it in a browser. */
            'pay_url' => $order?->isPayable() ? route('payment.start', $order->order_number) : null,
        ];
    }
}
