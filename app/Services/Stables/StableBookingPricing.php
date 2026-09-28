<?php

namespace App\Services\Stables;

use App\Enums\FeeType;
use App\Models\ServiceFeeSetting;
use App\Models\SiteSetting;
use App\Models\Stable;
use App\Models\StableOffering;
use App\Models\StablePackage;
use App\Services\Orders\OrderPricing;
use App\Services\Orders\PriceBreakdown;

/**
 * What a booking costs, by the same rules as every other order: the price (per rider × riders),
 * our service fee on top (the site-wide fee rule), VAT on both; the stable's own commission comes
 * out of the price, never added to what the customer pays. A free session carries none of it.
 */
class StableBookingPricing
{
    public function __construct(private readonly OrderPricing $orders) {}

    public function quote(StableOffering $offering, int $riders, ?Stable $stable = null): PriceBreakdown
    {
        return $this->quotePrice((int) round((float) $offering->price * 1000) * max(1, $riders), $stable ?? $offering->stable);
    }

    /** A lesson package: its own price, by the same rules. */
    public function quotePackage(StablePackage $package): PriceBreakdown
    {
        return $this->quotePrice((int) round((float) $package->price * 1000), $package->stable);
    }

    /** The breakdown of a price in baisa sold by a stable. */
    public function quotePrice(int $price, Stable $stable): PriceBreakdown
    {
        $currency = SiteSetting::currency()['code'];

        if ($price <= 0) {
            return new PriceBreakdown(0, 0, 0, 0, 0.0, $currency);
        }

        $feeSetting = ServiceFeeSetting::resolveGlobal();
        $fee = $feeSetting?->calculateBaisa($price) ?? 0;
        $commission = $this->commission($stable, $price);
        $rate = $this->orders->vatRate();

        return new PriceBreakdown(
            price: $price,
            fee: $fee,
            vatOnPrice: $this->vat($price, $rate),
            vatOnFee: $this->vat($fee, $rate),
            vatRate: $rate,
            currency: $currency,
            feeSetting: $feeSetting,
            commission: $commission,
            vatOnCommission: $this->orders->vatOnCommission() ? $this->vat($commission, $rate) : 0,
        );
    }

    /**
     * The service_orders columns for a booking: the breakdown plus the stable's commission rule
     * (there is no CommissionSetting behind it, so the breakdown cannot fill those two).
     *
     * @return array<string, mixed>
     */
    public function orderAttributes(PriceBreakdown $quote, Stable $stable): array
    {
        return [
            ...$quote->toOrderAttributes(),
            'commission_type' => $quote->price > 0 ? $stable->commission_type?->value : null,
            'commission_value' => $quote->price > 0 ? $stable->commission_value : null,
        ];
    }

    /** Percentage of the price, or a fixed amount per booking (never more than the price). */
    private function commission(Stable $stable, int $priceBaisa): int
    {
        if (! $stable->hasCommission()) {
            return 0;
        }

        return $stable->commission_type === FeeType::Percentage
            ? (int) round($priceBaisa * (float) $stable->commission_value / 100, 0, PHP_ROUND_HALF_UP)
            : min($priceBaisa, (int) round((float) $stable->commission_value * 1000));
    }

    private function vat(int $baisa, float $rate): int
    {
        return (int) round($baisa * $rate / 100, 0, PHP_ROUND_HALF_UP);
    }
}
