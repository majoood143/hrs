<?php

namespace App\Services\Orders;

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
}
