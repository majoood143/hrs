<?php

namespace App\Services\Stables;

use App\Enums\FeeType;
use App\Models\PromoCode;
use App\Models\ServiceFeeSetting;
use App\Models\SiteSetting;
use App\Models\Stable;
use App\Models\StableOffering;
use App\Models\StablePackage;
use App\Services\Orders\OrderPricing;
use App\Services\Orders\PriceBreakdown;

/**
 * What a booking costs: the price (per rider × riders), our service fee on top (the site-wide fee
 * rule); the stable's own commission comes out of the price, never added to what the customer pays.
 * A free session carries none of it.
 *
 * VAT follows who supplies what. The stable supplies the session (we collect for it as its agent),
 * so VAT on the price is charged only when the stable is VAT-registered. We supply the fee (to the
 * customer) and the commission (to the stable), so VAT on those follows our own registration.
 */
class StableBookingPricing
{
    public function __construct(private readonly OrderPricing $orders) {}

    /**
     * `$withFee` false: sold at the stable's desk (the POS), where we take the commission only.
     * `$extras` (baisa, `StableExtra::totalBaisa()`) is part of the price, so fee, VAT, commission and
     * the promo code's discount apply to it too.
     */
    public function quote(StableOffering $offering, int $riders, ?Stable $stable = null, ?PromoCode $promo = null, bool $withFee = true, int $extras = 0): PriceBreakdown
    {
        return $this->quotePrice((int) round((float) $offering->price * 1000) * max(1, $riders) + $extras, $stable ?? $offering->stable, $promo, $withFee);
    }

    /** A lesson package: its own price, by the same rules. */
    public function quotePackage(StablePackage $package, ?PromoCode $promo = null, bool $withFee = true): PriceBreakdown
    {
        return $this->quotePrice((int) round((float) $package->price * 1000), $package->stable, $promo, $withFee);
    }

    /**
     * The promo code typed, if any, checked for this stable and phone. `$lock` (inside the order's
     * transaction) holds its row while its uses are counted, so two orders cannot both take the last.
     *
     * @throws BookingUnavailable
     */
    public function promo(?string $code, Stable $stable, ?string $phone, bool $lock = false): ?PromoCode
    {
        if (blank($code)) {
            return null;
        }

        $promo = PromoCode::enabled()
            ? PromoCode::query()->where('code', PromoCode::normalize($code))->when($lock, fn ($query) => $query->lockForUpdate())->first()
            : null;

        if (! $promo) {
            throw new BookingUnavailable(__('promo_codes.errors.invalid'));
        }

        $promo->assertUsable($stable, $phone);

        return $promo;
    }

    /** The breakdown of a price in baisa sold by a stable, less the promo code's discount. */
    public function quotePrice(int $price, Stable $stable, ?PromoCode $promo = null, bool $withFee = true): PriceBreakdown
    {
        $currency = SiteSetting::currency()['code'];
        $discount = $promo?->discountOn($price) ?? 0;
        $price -= $discount;

        if ($price <= 0) {
            return new PriceBreakdown(0, 0, 0, 0, 0.0, $currency, discount: $discount);
        }

        $feeSetting = $withFee ? ServiceFeeSetting::resolveGlobal() : null;
        $fee = $feeSetting?->calculateBaisa($price) ?? 0;
        $commission = $this->commission($stable, $price);
        $ours = $this->orders->vatRate();
        $stables = $stable->vat_registered ? $this->orders->standardRate() : 0.0;

        return new PriceBreakdown(
            price: $price,
            fee: $fee,
            vatOnPrice: $this->vat($price, $stables),
            vatOnFee: $this->vat($fee, $ours),
            vatRate: max($ours, $stables),
            currency: $currency,
            feeSetting: $feeSetting,
            commission: $commission,
            vatOnCommission: $this->orders->vatOnCommission() ? $this->vat($commission, $ours) : 0,
            discount: $discount,
        );
    }

    /**
     * The service_orders columns for a booking: the breakdown plus the stable's commission rule
     * (there is no CommissionSetting behind it, so the breakdown cannot fill those two).
     *
     * @return array<string, mixed>
     */
    public function orderAttributes(PriceBreakdown $quote, Stable $stable, ?PromoCode $promo = null): array
    {
        return [
            ...$quote->toOrderAttributes(),
            'commission_type' => $quote->price > 0 ? $stable->commission_type?->value : null,
            'commission_value' => $quote->price > 0 ? $stable->commission_value : null,
            'stable_vat_number' => $quote->vatOnPrice > 0 ? $stable->vat_number : null,
            'promo_code_id' => $quote->discount > 0 ? $promo?->getKey() : null,
            'discount_amount' => PriceBreakdown::toOmr($quote->discount),
        ];
    }

    /** Percentage of the price, or a fixed amount per booking (never more than the price). */
    private function commission(Stable $stable, int $priceBaisa): int
    {
        if (! $stable->hasCommission()) {
            return 0;
        }

        return $stable->commission_type === FeeType::Percentage
            ? min($priceBaisa, (int) round($priceBaisa * (float) $stable->commission_value / 100, 0, PHP_ROUND_HALF_UP))
            : min($priceBaisa, (int) round((float) $stable->commission_value * 1000));
    }

    private function vat(int $baisa, float $rate): int
    {
        return (int) round($baisa * $rate / 100, 0, PHP_ROUND_HALF_UP);
    }
}
