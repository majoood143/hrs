<?php

namespace App\Models;

use App\Enums\StableBookingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A customer's package: pending until its order is paid, then active until expires_at. Its sessions
 * are used by the bookings made with it that hold places, so cancelling one gives it back.
 */
class StablePackagePurchase extends Model
{
    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const CANCELLED = 'cancelled';

    protected $guarded = [];

    protected $attributes = [
        'status' => self::PENDING,
    ];

    protected function casts(): array
    {
        return [
            'sessions' => 'integer',
            'validity_days' => 'integer',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $purchase): void {
            if (! $purchase->reference) {
                do {
                    $reference = 'PK-'.strtoupper(Str::random(8));
                } while (static::query()->where('reference', $reference)->exists());

                $purchase->reference = $reference;
            }
        });
    }

    public function stable(): BelongsTo
    {
        return $this->belongsTo(Stable::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(StablePackage::class, 'stable_package_id');
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(StableOffering::class, 'stable_offering_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(StableBooking::class);
    }

    /** Active and not expired. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', self::ACTIVE)->where('expires_at', '>', now());
    }

    public function sessionsUsed(): int
    {
        return (int) $this->bookings()->whereIn('status', StableBookingStatus::holdingValues())->sum('riders');
    }

    public function sessionsLeft(): int
    {
        return max(0, $this->sessions - $this->sessionsUsed());
    }

    public function isUsable(): bool
    {
        return $this->status === self::ACTIVE && $this->expires_at?->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->status === self::ACTIVE && $this->expires_at?->isPast();
    }

    /** "Active", "Expired", "Awaiting payment" or "Cancelled", as the customer and the stable see it. */
    public function statusKey(): string
    {
        return $this->isExpired() ? 'expired' : $this->status;
    }
}
