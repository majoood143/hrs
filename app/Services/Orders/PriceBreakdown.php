<?php

namespace App\Services\Orders;

use App\Models\CommissionSetting;
use App\Models\ServiceFeeSetting;

/**
 * The money of one order, in baisa (integers, so nothing drifts by a rounding error).
 *
 * total = price + fee + vatOnPrice + vatOnFee
 */
final class PriceBreakdown
{
    public function __construct(
        public readonly int $price,
        public readonly int $fee,
        public readonly int $vatOnPrice,
        public readonly int $vatOnFee,
        public readonly float $vatRate,
        public readonly string $currency,
        public readonly ?ServiceFeeSetting $feeSetting = null,
        /** taken out of the client's share (the price), not added to what the customer pays */
        public readonly int $commission = 0,
        public readonly ?CommissionSetting $commissionSetting = null,
        /** VAT on the commission, when the setting is on: owed to us by the client, not paid by the customer */
        public readonly int $vatOnCommission = 0,
    ) {}

    public function total(): int
    {
        return $this->price + $this->fee + $this->vatOnPrice + $this->vatOnFee;
    }

    public function isFree(): bool
    {
        return $this->total() === 0;
    }

    public static function toOmr(int $baisa): string
    {
        return number_format($baisa / 1000, 3, '.', '');
    }

    /**
     * The service_orders columns this breakdown fills.
     *
     * @return array<string, mixed>
     */
    public function toOrderAttributes(): array
    {
        return [
            'currency' => $this->currency,
            'price' => self::toOmr($this->price),
            'fee_amount' => self::toOmr($this->fee),
            'vat_rate' => $this->vatRate,
            'vat_on_price' => self::toOmr($this->vatOnPrice),
            'vat_on_fee' => self::toOmr($this->vatOnFee),
            'total' => self::toOmr($this->total()),
            'service_fee_setting_id' => $this->feeSetting?->getKey(),
            'service_fee_type' => $this->feeSetting?->fee_type->value,
            'service_fee_value' => $this->feeSetting?->fee_value,
            'commission_amount' => self::toOmr($this->commission),
            'commission_setting_id' => $this->commissionSetting?->getKey(),
            'commission_type' => $this->commissionSetting?->commission_type->value,
            'commission_value' => $this->commissionSetting?->commission_value,
            'vat_on_commission' => self::toOmr($this->vatOnCommission),
        ];
    }
}
