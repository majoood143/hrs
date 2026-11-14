<?php

namespace App\Services\Reports;

use App\Models\BookingSlot;
use App\Models\ServiceOrder;
use App\Services\Orders\OrderMoney;
use Carbon\CarbonImmutable;

/**
 * One entry of the income reports, in baisa (integers): an order's payment, or a refund on it
 * (dated the day it was recorded and carrying only what it changed, so it never rewrites the
 * period the payment fell in).
 *
 *   total = price + vatOnPrice + fee + vatOnFee            what the customer paid (0 on a refund entry)
 *   clientGross = price + vatOnPrice                       the client's share
 *   dueToUs = fee + vatOnFee + commission + vatOnCommission   what the client owes us (fee net of any refunded)
 *   clientKeeps = total - refunded - dueToUs               what stays with the client
 */
final class IncomeRow
{
    public const PAYMENT = 'payment';

    public const REFUND = 'refund';

    public function __construct(
        public readonly int $orderId,
        public readonly string $orderNumber,
        /** the entry's day: the payment's, or the refund's */
        public readonly CarbonImmutable $paidAt,
        public readonly ?int $serviceId,
        public readonly ?int $formId,
        public readonly ?string $gateway,
        public readonly int $total,
        public readonly int $price,
        public readonly int $vatOnPrice,
        public readonly int $fee,
        public readonly int $vatOnFee,
        /** the commission actually earned, after any refund of the client's share */
        public readonly int $commission,
        public readonly int $refunded,
        /** the VAT on the commission, shrunk by refunds the same way */
        public readonly int $vatOnCommission = 0,
        public readonly string $entry = self::PAYMENT,
        /** the gateway's estimated charge on the payment (GatewayCosts, fixed when paid); 0 on a refund entry */
        public readonly int $gatewayCost = 0,
    ) {}

    /**
     * The order's payment and its refunds.
     *
     * @return list<self>
     */
    public static function entriesFor(ServiceOrder $order): array
    {
        $baisa = fn ($amount): int => (int) round((float) $amount * 1000);

        return array_map(fn (array $e) => new self(
            orderId: $order->getKey(),
            orderNumber: $order->order_number,
            // in Muscat time, so days and months group by the local calendar
            paidAt: CarbonImmutable::instance($e['date'])->setTimezone(BookingSlot::timezone()),
            serviceId: $order->service_id,
            formId: $order->form_id,
            gateway: $order->payment_method?->value,
            total: $e['total'],
            price: $e['entry'] === 'sale' ? $baisa($order->price) : 0,
            vatOnPrice: $e['entry'] === 'sale' ? $baisa($order->vat_on_price) : 0,
            fee: $e['fee'],
            vatOnFee: $e['vat_on_fee'],
            commission: $e['commission'],
            refunded: $e['refunded'],
            vatOnCommission: $e['vat_on_commission'],
            entry: $e['entry'] === 'sale' ? self::PAYMENT : self::REFUND,
            gatewayCost: $e['entry'] === 'sale' ? $baisa($order->gateway_cost) : 0,
        ), OrderMoney::entries($order, $order->paid_at));
    }

    public function isRefund(): bool
    {
        return $this->entry === self::REFUND;
    }

    /** What the customer's money came to (a refund entry: what went back, negative). */
    public function collected(): int
    {
        return $this->total - $this->refunded;
    }

    public function clientGross(): int
    {
        return $this->price + $this->vatOnPrice;
    }

    public function dueToUs(): int
    {
        return $this->fee + $this->vatOnFee + $this->commission + $this->vatOnCommission;
    }

    /** What we keep once the gateway's charge is paid. */
    public function netToUs(): int
    {
        return $this->dueToUs() - $this->gatewayCost;
    }

    public function clientKeeps(): int
    {
        return $this->total - $this->refunded - $this->dueToUs();
    }
}
