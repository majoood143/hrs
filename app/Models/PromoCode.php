<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Services\Stables\BookingUnavailable;
use App\Support\Money;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A discount code for a stable session or lesson package (the booking project's promo codes). The
 * discount comes off the price, so the service fee, the stable's commission and VAT are worked out
 * on what is left. A use is an order carrying the code that was not cancelled or rejected, so an
 * expired checkout gives its use back.
 */
class PromoCode extends Model
{
    use SoftDeletes;

    public const PERCENTAGE = 'percentage';

    public const FIXED = 'fixed';

    public const SETTING = 'module_promo_codes_enabled';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:3',
            'max_discount_amount' => 'decimal:3',
            'max_uses' => 'integer',
            'is_active' => 'boolean',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    /** General Settings → Modules → Promo codes. */
    public static function enabled(): bool
    {
        return (bool) SiteSetting::get(self::SETTING, true);
    }

    public static function normalize(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }

    public function stable(): BelongsTo
    {
        return $this->belongsTo(Stable::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function usedCount(): int
    {
        return $this->orders()->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Rejected->value])->count();
    }

    /**
     * Whether this code may be used at this stable by this phone.
     *
     * @throws BookingUnavailable when it cannot (message for the customer)
     */
    public function assertUsable(Stable $stable, ?string $phone): void
    {
        $refuse = fn (string $key) => throw new BookingUnavailable(__('promo_codes.errors.'.$key));

        match (true) {
            ! $this->is_active => $refuse('invalid'),
            $this->stable_id !== null && (int) $this->stable_id !== (int) $stable->getKey() => $refuse('other_stable'),
            $this->valid_from !== null && now()->lt($this->valid_from) => $refuse('not_started'),
            $this->valid_until !== null && now()->gt($this->valid_until) => $refuse('expired'),
            filled($this->restricted_mobile) && PhoneNumber::normalize($phone) !== $this->restricted_mobile => $refuse('other_mobile'),
            $this->max_uses !== null && $this->usedCount() >= $this->max_uses => $refuse('used_up'),
            default => null,
        };
    }

    /** Never more than the price; a percentage never more than its cap. */
    public function discountOn(int $priceBaisa): int
    {
        $value = (float) $this->discount_value;

        $discount = $this->discount_type === self::PERCENTAGE
            ? (int) round($priceBaisa * $value / 100, 0, PHP_ROUND_HALF_UP)
            : (int) round($value * 1000);

        if ($this->discount_type === self::PERCENTAGE && $this->max_discount_amount !== null) {
            $discount = min($discount, (int) round((float) $this->max_discount_amount * 1000));
        }

        return max(0, min($discount, $priceBaisa));
    }

    /** "10%" or "OMR 5.000". */
    public function label(): string
    {
        return $this->discount_type === self::PERCENTAGE
            ? rtrim(rtrim(number_format((float) $this->discount_value, 3, '.', ''), '0'), '.').'%'
            : Money::format((int) round((float) $this->discount_value * 1000));
    }
}
