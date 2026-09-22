<?php

namespace App\Services\Orders;

use App\Models\CommissionSetting;
use App\Models\Service;
use App\Models\ServiceFeeSetting;
use App\Models\SiteSetting;
use Carbon\CarbonInterface;

/**
 * Works out what a customer pays for a service: its fixed price, the service fee on top,
 * and VAT on both. A free service (price 0) carries no fee and no VAT.
 */
class OrderPricing
{
    public function quote(Service $service, ?int $formId = null, ?CarbonInterface $on = null): PriceBreakdown
    {
        $price = (int) round((float) $service->price * 1000);
        $currency = SiteSetting::currency()['code'];

        if ($price <= 0) {
            return new PriceBreakdown(0, 0, 0, 0, 0.0, $currency);
        }

        $feeSetting = ServiceFeeSetting::resolveFor($service, $formId, $on);
        $fee = $feeSetting?->calculateBaisa($price) ?? 0;

        $commissionSetting = CommissionSetting::resolveFor($service, $formId, $on);
        $commission = $commissionSetting?->calculateBaisa($price) ?? 0;

        $rate = $this->vatRate();

        return new PriceBreakdown(
            price: $price,
            fee: $fee,
            vatOnPrice: $this->vat($price, $rate),
            vatOnFee: $this->vat($fee, $rate),
            vatRate: $rate,
            currency: $currency,
            feeSetting: $feeSetting,
            commission: $commission,
            commissionSetting: $commissionSetting,
            vatOnCommission: $this->vatOnCommission() ? $this->vat($commission, $rate) : 0,
        );
    }

    /** Whether VAT is charged on our commission too (a tax question: off unless your accountant says so). */
    public function vatOnCommission(): bool
    {
        return (bool) SiteSetting::get('vat.on_commission', false);
    }

    /** The VAT rate in percent, 0 when VAT is switched off. */
    public function vatRate(): float
    {
        $enabled = (bool) SiteSetting::get('vat.enabled', config('payments.vat.enabled', true));

        return $enabled ? (float) SiteSetting::get('vat.rate', config('payments.vat.rate', 5.0)) : 0.0;
    }

    private function vat(int $baisa, float $rate): int
    {
        return (int) round($baisa * $rate / 100, 0, PHP_ROUND_HALF_UP);
    }
}
