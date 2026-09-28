<?php

namespace App\Models;

use App\Enums\StableBookingStatus;
use App\Models\Concerns\LogsStableActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A customer's places in one booking slot. The money side (price, fee, VAT, commission, payment,
 * refunds) lives on the linked ServiceOrder, like every other paid order; this row is what the
 * stable sees: which slot, how many riders, and who they are.
 */
class StableBooking extends Model
{
    use LogsStableActivity;

    /** @var list<string> */
    protected static $recordEvents = ['updated'];

    protected $guarded = [];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => StableBookingStatus::class,
            'riders' => 'integer',
            'riders_data' => 'array',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'attended_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $booking): void {
            $booking->reference ??= static::newReference();
        });
    }

    /** BK-XXXXXXXX, from the same look-alike-free alphabet as order numbers. */
    public static function newReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $reference = 'BK-'.collect(range(1, 8))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
        } while (static::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public function stable(): BelongsTo
    {
        return $this->belongsTo(Stable::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(BookingSlot::class, 'booking_slot_id');
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(StableOffering::class, 'stable_offering_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function packagePurchase(): BelongsTo
    {
        return $this->belongsTo(StablePackagePurchase::class, 'stable_package_purchase_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(StableReview::class);
    }

    public function holdsPlaces(): bool
    {
        return in_array($this->status->value, StableBookingStatus::holdingValues(), true);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [StableBookingStatus::Pending, StableBookingStatus::Confirmed], true);
    }

    /** The riders' names, for lists and messages. */
    public function riderNames(): string
    {
        return collect($this->riders_data ?? [])->pluck('name')->filter()->implode(', ');
    }

    /** @return list<string> */
    protected function stableActivityAttributes(): array
    {
        return ['status', 'cancellation_source', 'cancellation_reason', 'attended_at'];
    }
}
