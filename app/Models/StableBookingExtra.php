<?php

namespace App\Models;

use App\Enums\StableBookingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An extra a booking took, copied (names, unit price) as it was when booked. */
class StableBookingExtra extends Model
{
    protected $fillable = ['stable_booking_id', 'stable_extra_id', 'en_name', 'ar_name', 'unit_price', 'quantity', 'amount'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:3',
            'quantity' => 'integer',
            'amount' => 'decimal:3',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(StableBooking::class, 'stable_booking_id');
    }

    public function extra(): BelongsTo
    {
        return $this->belongsTo(StableExtra::class, 'stable_extra_id')->withTrashed();
    }

    /** Lines of bookings that went ahead (confirmed, attended or a no-show), the ones that count as sold. */
    public function scopeSold(Builder $query): Builder
    {
        return $query->whereHas('booking', fn (Builder $b) => $b->whereIn('status', [
            StableBookingStatus::Confirmed->value, StableBookingStatus::Completed->value, StableBookingStatus::NoShow->value,
        ]));
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' && filled($this->ar_name) ? $this->ar_name : (string) $this->en_name;
    }
}
