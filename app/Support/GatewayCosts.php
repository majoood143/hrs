<?php

namespace App\Support;

use App\Enums\PaymentGateway;
use App\Models\SiteSetting;

/**
 * What each payment gateway charges us per payment, as entered in admin → Payment Gateways
 * (a percentage of the amount plus a fixed amount). An estimate: the gateway's own statement is
 * the real figure. It is worked out once, when an order is paid, and kept on the order.
 */
final class GatewayCosts
{
    public const GATEWAYS = ['thawani', 'ompay', 'nbo', 'ccavenue'];

    /** @return array<string, array{percent: float, fixed: float}> */
    public static function rates(): array
    {
        $saved = SiteSetting::get('payments.gateway_costs');
        $saved = is_string($saved) ? (json_decode($saved, true) ?: []) : (is_array($saved) ? $saved : []);

        return collect(self::GATEWAYS)->mapWithKeys(fn (string $g) => [$g => [
            'percent' => max(0.0, (float) ($saved[$g]['percent'] ?? 0)),
            'fixed' => max(0.0, (float) ($saved[$g]['fixed'] ?? 0)),
        ]])->all();
    }

    /** The estimated charge on a payment of $totalBaisa through $gateway, in baisa. */
    public static function estimate(?PaymentGateway $gateway, int $totalBaisa): int
    {
        $rate = self::rates()[$gateway?->value] ?? null;

        if (! $rate || $totalBaisa <= 0) {
            return 0;
        }

        return (int) round($totalBaisa * $rate['percent'] / 100, 0, PHP_ROUND_HALF_UP) + (int) round($rate['fixed'] * 1000);
    }
}
