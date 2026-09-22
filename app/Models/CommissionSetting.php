<?php

namespace App\Models;

use App\Enums\FeeType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * A commission taken out of the client's share of an order (its price, before VAT). It is not added to
 * what the customer pays. As with fees, the most specific active rule wins: a form, then a service,
 * then global. Percentage or fixed; a fixed commission never exceeds the price.
 */
class CommissionSetting extends Model
{
    use HasTranslations;

    protected $guarded = [];

    /** @var array<int, string> */
    public array $translatable = ['name', 'description'];

    protected $attributes = [
        'commission_type' => 'percentage',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'commission_type' => FeeType::class,
            'commission_value' => 'decimal:3',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEffectiveOn(Builder $query, CarbonInterface|string $date): Builder
    {
        $date = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        return $query
            ->where(fn (Builder $q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date))
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));
    }

    public static function resolveFor(Service $service, ?int $formId = null, ?CarbonInterface $on = null): ?self
    {
        return static::query()
            ->active()
            ->effectiveOn($on ?? now())
            ->where(function (Builder $q) use ($service, $formId) {
                if ($formId !== null) {
                    $q->where('form_id', $formId);
                }

                $q->orWhere(fn (Builder $q) => $q->whereNull('form_id')->where('service_id', $service->getKey()))
                    ->orWhere(fn (Builder $q) => $q->whereNull('form_id')->whereNull('service_id'));
            })
            ->orderByRaw('CASE WHEN form_id IS NOT NULL THEN 1 WHEN service_id IS NOT NULL THEN 2 ELSE 3 END')
            ->orderByDesc('id')
            ->first();
    }

    /** The commission in baisa for a price in baisa. */
    public function calculateBaisa(int $priceBaisa): int
    {
        if ($this->commission_type === FeeType::Percentage) {
            return (int) round($priceBaisa * (float) $this->commission_value / 100, 0, PHP_ROUND_HALF_UP);
        }

        return min($priceBaisa, (int) round((float) $this->commission_value * 1000));
    }

    public function getFormattedValueAttribute(): string
    {
        return $this->commission_type === FeeType::Percentage
            ? rtrim(rtrim(number_format((float) $this->commission_value, 3), '0'), '.').'%'
            : number_format((float) $this->commission_value, 3).' '.SiteSetting::currency()['code'];
    }

    public function getScopeNameAttribute(): string
    {
        return match (true) {
            $this->form_id !== null => __('admin_service_fee_setting.scope.form', ['id' => $this->form_id]),
            $this->service_id !== null => __('admin_service_fee_setting.scope.service', ['name' => $this->service?->name ?? $this->service_id]),
            default => __('admin_service_fee_setting.scope.global'),
        };
    }
}
