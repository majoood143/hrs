<?php

namespace App\Services\Orders;

use App\Enums\PaymentStatus;
use App\Events\ServiceOrderRefunded;
use App\Models\OrderRefund;
use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\DB;

/**
 * Records money handed back to a customer. The refund is made at the payment gateway (or by bank
 * transfer); this keeps the record and updates the books. What may be refunded is the price and its
 * VAT, minus earlier refunds: the service fee (and its VAT) is never refunded. Once everything
 * refundable has gone back, the order's payment status becomes "refunded".
 */
class OrderRefundService
{
    /** What a refund may still return, in baisa. */
    public function refundable(ServiceOrder $order): int
    {
        return (int) round($order->refundableAmount() * 1000);
    }

    /**
     * @param  int  $baisa  the amount, in baisa
     *
     * @throws RefundException
     */
    public function record(ServiceOrder $order, int $baisa, ?string $reason = null, ?int $userId = null, string $method = 'manual', ?string $reference = null): OrderRefund
    {
        if ($baisa <= 0) {
            throw new RefundException(__('admin_service_order.refund.amount_positive'));
        }

        $refund = DB::transaction(function () use ($order, $baisa, $reason, $userId, $method, $reference) {
            $locked = ServiceOrder::query()->whereKey($order->getKey())->lockForUpdate()->first();

            if (! $locked || ! $locked->isPaid()) {
                throw new RefundException(__('admin_service_order.refund.not_paid'));
            }

            $refundable = $this->refundable($locked);

            if ($baisa > $refundable) {
                throw new RefundException(__('admin_service_order.refund.too_much', ['max' => SiteSetting::currency()['code'].' '.number_format($refundable / 1000, 3)]));
            }

            $refund = $locked->refunds()->create([
                'amount' => number_format($baisa / 1000, 3, '.', ''),
                'reason' => $reason,
                'method' => in_array($method, ['gateway', 'manual'], true) ? $method : 'manual',
                'reference' => $reference,
                'recorded_by' => $userId,
            ]);

            $locked->forceFill(['refunded_amount' => number_format(((int) round((float) $locked->refunded_amount * 1000) + $baisa) / 1000, 3, '.', '')]);

            // nothing left to give back: the order is fully refunded
            if ($this->refundable($locked) === 0) {
                $locked->payment_status = PaymentStatus::Refunded;
            }

            $locked->save();
            $locked->recordEvent('refunded', __('orders.events.refunded'), ['amount' => $refund->amount, 'method' => $refund->method], userId: $userId);

            return $refund;
        });

        $order->refresh();
        ServiceOrderRefunded::dispatch($order, $refund);

        return $refund;
    }
}
