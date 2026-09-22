<?php

namespace App\Services\Reports;

use App\Models\ServiceOrder;
use App\Services\Orders\OrderMoney;
use Carbon\CarbonImmutable;

/**
 * One paid order as the income reports see it, in baisa (integers).
 *
 *   total = price + vatOnPrice + fee + vatOnFee            what the customer paid, into the client's account
 *   clientGross = price + vatOnPrice                       the client's share
 *   dueToUs = fee + vatOnFee + commission + vatOnCommission   what the client owes us for this order
 *   clientKeeps = total - refunded - dueToUs               what stays with the client
 */
final class IncomeRow
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $orderNumber,
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
    ) {}

    public static function fromOrder(ServiceOrder $order): self
    {
        $baisa = fn ($amount): int => (int) round((float) $amount * 1000);

        return new self(
            orderId: $order->getKey(),
            orderNumber: $order->order_number,
            paidAt: CarbonImmutable::instance($order->paid_at),
            serviceId: $order->service_id,
            formId: $order->form_id,
            gateway: $order->payment_method?->value,
            total: $baisa($order->total),
            price: $baisa($order->price),
            vatOnPrice: $baisa($order->vat_on_price),
            fee: $baisa($order->fee_amount),
            vatOnFee: $baisa($order->vat_on_fee),
            commission: OrderMoney::earnedCommission($baisa($order->commission_amount), $baisa($order->price) + $baisa($order->vat_on_price), $baisa($order->refunded_amount)),
            refunded: $baisa($order->refunded_amount),
            vatOnCommission: OrderMoney::earnedCommission($baisa($order->vat_on_commission), $baisa($order->price) + $baisa($order->vat_on_price), $baisa($order->refunded_amount)),
        );
    }

    public function clientGross(): int
    {
        return $this->price + $this->vatOnPrice;
    }

    public function dueToUs(): int
    {
        return $this->fee + $this->vatOnFee + $this->commission + $this->vatOnCommission;
    }

    public function clientKeeps(): int
    {
        return $this->total - $this->refunded - $this->dueToUs();
    }
}
