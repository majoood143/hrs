<?php

namespace App\Services\Reports;

use App\Enums\PaymentStatus;
use App\Models\ServiceOrder;
use App\Services\Orders\OrderMoney;
use Carbon\CarbonImmutable;

/**
 * One booking that carries money, as a stable's statement sees it, in baisa (integers).
 *
 *   stableShare = price + vatOnPrice - refunded - commission - vatOnCommission   what the stable earns
 *   ourShare    = fee + vatOnFee + commission + vatOnCommission                  what we earn
 *
 * Who holds the money decides who owes whom: a booking paid into our account leaves us owing the
 * stable its share; one paid into the stable's own account, or at the stable, leaves the stable
 * owing us ours. `net` is that, seen from the stable (positive: we owe it).
 */
final class StableLedgerRow
{
    public const ONLINE_OURS = 'online_ours';

    public const ONLINE_OWN = 'online_own';

    public const AT_STABLE = 'at_stable';

    public function __construct(
        public readonly int $orderId,
        public readonly string $orderNumber,
        public readonly ?string $bookingReference,
        public readonly string $service,
        public readonly ?string $sessionDate,
        public readonly int $riders,
        public readonly CarbonImmutable $date,
        public readonly string $kind,
        public readonly int $total,
        public readonly int $price,
        public readonly int $vatOnPrice,
        public readonly int $fee,
        public readonly int $vatOnFee,
        public readonly int $commission,
        public readonly int $vatOnCommission,
        public readonly int $refunded,
        public readonly ?int $stableId = null,
    ) {}

    public static function fromOrder(ServiceOrder $order): self
    {
        $baisa = fn ($amount): int => (int) round((float) $amount * 1000);
        $booking = $order->stableBooking;
        $clientShare = $baisa($order->price) + $baisa($order->vat_on_price);
        $refunded = $baisa($order->refunded_amount);

        $kind = match (true) {
            $order->payment_status === PaymentStatus::OnSite => self::AT_STABLE,
            $order->collected_by === 'stable' => self::ONLINE_OWN,
            default => self::ONLINE_OURS,
        };

        $date = $kind === self::AT_STABLE
            ? ($booking?->attended_at ?? $order->completed_at ?? $order->created_at)
            : ($order->paid_at ?? $order->created_at);

        return new self(
            orderId: $order->getKey(),
            orderNumber: $order->order_number,
            bookingReference: $booking?->reference,
            service: (string) ($booking?->offering?->name ?? $order->serviceName()),
            sessionDate: $booking?->slot?->date?->toDateString(),
            riders: (int) ($booking?->riders ?? 0),
            date: CarbonImmutable::instance($date),
            kind: $kind,
            total: $baisa($order->total),
            price: $baisa($order->price),
            vatOnPrice: $baisa($order->vat_on_price),
            fee: $baisa($order->fee_amount),
            vatOnFee: $baisa($order->vat_on_fee),
            commission: OrderMoney::earnedCommission($baisa($order->commission_amount), $clientShare, $refunded),
            vatOnCommission: OrderMoney::earnedCommission($baisa($order->vat_on_commission), $clientShare, $refunded),
            refunded: $refunded,
            stableId: $order->stable_id,
        );
    }

    public function heldByUs(): bool
    {
        return $this->kind === self::ONLINE_OURS;
    }

    public function stableShare(): int
    {
        return $this->price + $this->vatOnPrice - $this->refunded - $this->commission - $this->vatOnCommission;
    }

    public function ourShare(): int
    {
        return $this->fee + $this->vatOnFee + $this->commission + $this->vatOnCommission;
    }

    /** What the customer's money came to after refunds. */
    public function collected(): int
    {
        return $this->total - $this->refunded;
    }

    public function owedToStable(): int
    {
        return $this->heldByUs() ? $this->stableShare() : 0;
    }

    public function owedByStable(): int
    {
        return $this->heldByUs() ? 0 : $this->ourShare();
    }

    public function net(): int
    {
        return $this->owedToStable() - $this->owedByStable();
    }
}
