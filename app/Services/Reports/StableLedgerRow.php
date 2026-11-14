<?php

namespace App\Services\Reports;

use App\Enums\PaymentStatus;
use App\Models\BookingSlot;
use App\Models\ServiceOrder;
use App\Services\Orders\OrderMoney;
use Carbon\CarbonImmutable;

/**
 * One entry of a stable's account with us, in baisa (integers): a booking's sale, or a refund on it
 * (dated the day it was recorded, carrying only what it changed, so it never rewrites the period the
 * sale fell in). fromOrder() is the booking as it stands now, sale and refunds together.
 *
 *   stableShare = price + vatOnPrice - clientRefunded - commission - vatOnCommission   what the stable earns
 *   ourShare    = fee + vatOnFee + commission + vatOnCommission                        what we earn (fee net of any refunded)
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

    public const SALE = 'sale';

    public const REFUND = 'refund';

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
        /** the part of $refunded that came out of the client's share (the rest was our fee) */
        public readonly int $clientRefunded = 0,
        public readonly string $entry = self::SALE,
    ) {}

    /** The booking as it stands now (sale and refunds together), e.g. for the booking's own page. */
    public static function fromOrder(ServiceOrder $order): self
    {
        $refunded = (int) round((float) $order->refunded_amount * 1000);

        return self::make($order, self::SALE, self::saleDate($order), ['total' => self::baisa($order->total), 'refunded' => $refunded] + OrderMoney::afterRefunds($order, $refunded));
    }

    /**
     * The booking's dated entries: its sale, then one per refund.
     *
     * @return list<self>
     */
    public static function entriesFor(ServiceOrder $order): array
    {
        return array_map(
            fn (array $e) => self::make($order, $e['entry'], $e['date'] ?? self::saleDate($order), $e),
            OrderMoney::entries($order, self::saleDate($order)),
        );
    }

    private static function saleDate(ServiceOrder $order): mixed
    {
        return $order->payment_status === PaymentStatus::OnSite
            ? ($order->stableBooking?->attended_at ?? $order->completed_at ?? $order->created_at)
            : ($order->paid_at ?? $order->created_at);
    }

    private static function baisa(mixed $amount): int
    {
        return (int) round((float) $amount * 1000);
    }

    /** @param  array{total: int, refunded: int, client_refunded: int, fee: int, vat_on_fee: int, commission: int, vat_on_commission: int}  $money */
    private static function make(ServiceOrder $order, string $entry, mixed $date, array $money): self
    {
        $booking = $order->stableBooking;
        $sale = $entry === self::SALE;

        return new self(
            orderId: $order->getKey(),
            orderNumber: $order->order_number,
            bookingReference: $booking?->reference,
            service: (string) ($booking?->offering?->name ?? $order->serviceName()),
            sessionDate: $booking?->slot?->date?->toDateString(),
            riders: $sale ? (int) ($booking?->riders ?? 0) : 0,
            // in Muscat time, so days and months group by the local calendar
            date: CarbonImmutable::instance($date)->setTimezone(BookingSlot::timezone()),
            kind: match (true) {
                $order->payment_status === PaymentStatus::OnSite => self::AT_STABLE,
                $order->collected_by === 'stable' => self::ONLINE_OWN,
                default => self::ONLINE_OURS,
            },
            total: $money['total'],
            price: $sale ? self::baisa($order->price) : 0,
            vatOnPrice: $sale ? self::baisa($order->vat_on_price) : 0,
            fee: $money['fee'],
            vatOnFee: $money['vat_on_fee'],
            commission: $money['commission'],
            vatOnCommission: $money['vat_on_commission'],
            refunded: $money['refunded'],
            stableId: $order->stable_id,
            clientRefunded: $money['client_refunded'],
            entry: $entry,
        );
    }

    /** "Online, through us"…, or "Refund" for a refund entry. */
    public function label(): string
    {
        return __('stable_statement.kinds.'.($this->isRefund() ? self::REFUND : $this->kind));
    }

    public function isRefund(): bool
    {
        return $this->entry === self::REFUND;
    }

    public function heldByUs(): bool
    {
        return $this->kind === self::ONLINE_OURS;
    }

    public function stableShare(): int
    {
        return $this->price + $this->vatOnPrice - $this->clientRefunded - $this->commission - $this->vatOnCommission;
    }

    public function ourShare(): int
    {
        return $this->fee + $this->vatOnFee + $this->commission + $this->vatOnCommission;
    }

    /** What the customer's money came to after refunds (a refund entry: what went back). */
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
