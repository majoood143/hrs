<?php

namespace App\Services\Orders;

use App\Models\ServiceOrder;
use Carbon\CarbonInterface;

/**
 * The arithmetic shared by the order page and the income reports, in baisa (integers).
 */
class OrderMoney
{
    /**
     * The commission an order has really earned. It comes out of the client's share, so when part of
     * that share is refunded to the customer the commission shrinks with it; a fully refunded order
     * earns none. (The service fee is never refunded, so it never shrinks.)
     */
    public static function earnedCommission(int $commission, int $clientGross, int $refunded): int
    {
        if ($commission <= 0 || $clientGross <= 0) {
            return 0;
        }

        $kept = max(0, $clientGross - max(0, $refunded));

        return (int) round($commission * $kept / $clientGross, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * What an order comes to once $refunded baisa went back to the customer. Refunds take from the
     * client's share first, then (only when the fee is refundable) from our fee and its VAT; the
     * commission shrinks with the client's share.
     *
     * @return array{client_refunded: int, fee: int, vat_on_fee: int, commission: int, vat_on_commission: int}
     */
    public static function afterRefunds(ServiceOrder $order, int $refunded): array
    {
        $baisa = fn ($amount): int => (int) round((float) $amount * 1000);
        $clientShare = $baisa($order->price) + $baisa($order->vat_on_price);
        $fee = $baisa($order->fee_amount);
        $vatOnFee = $baisa($order->vat_on_fee);

        $client = min(max(0, $refunded), $clientShare);
        $feeBack = min(max(0, $refunded - $client), $fee + $vatOnFee);
        $vatBack = $fee + $vatOnFee > 0 ? (int) round($feeBack * $vatOnFee / ($fee + $vatOnFee), 0, PHP_ROUND_HALF_UP) : 0;

        return [
            'client_refunded' => $client,
            'fee' => $fee - ($feeBack - $vatBack),
            'vat_on_fee' => $vatOnFee - $vatBack,
            'commission' => self::earnedCommission($baisa($order->commission_amount), $clientShare, $client),
            'vat_on_commission' => self::earnedCommission($baisa($order->vat_on_commission), $clientShare, $client),
        ];
    }

    /**
     * The order as dated entries: the sale (as it was, before any refund) and one entry per refund
     * on the day it was recorded, carrying only what that refund changed. Their sum is the order
     * now, and a refund never rewrites the period the sale fell in.
     *
     * @return list<array{entry: string, date: CarbonInterface|null, total: int, refunded: int, client_refunded: int, fee: int, vat_on_fee: int, commission: int, vat_on_commission: int}>
     */
    public static function entries(ServiceOrder $order, $saleDate): array
    {
        $baisa = fn ($amount): int => (int) round((float) $amount * 1000);
        $before = self::afterRefunds($order, 0);
        $entries = [['entry' => 'sale', 'date' => $saleDate, 'total' => $baisa($order->total), 'refunded' => 0] + $before];
        $sum = 0;

        foreach ($order->refunds as $refund) {
            $sum += $baisa($refund->amount);
            $after = self::afterRefunds($order, $sum);
            $entries[] = ['entry' => 'refund', 'date' => $refund->created_at, 'total' => 0, 'refunded' => $baisa($refund->amount)]
                + array_map(fn (string $key) => $after[$key] - $before[$key], array_combine(array_keys($after), array_keys($after)));
            $before = $after;
        }

        return $entries;
    }
}
