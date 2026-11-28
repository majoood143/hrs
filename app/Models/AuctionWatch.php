<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A customer following a lot (they bid on it, or tapped "Watch"): they are told when it is about to close. */
class AuctionWatch extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['ending_notified_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(AuctionLot::class, 'auction_lot_id');
    }
}
