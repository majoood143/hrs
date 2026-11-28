<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row of a lot's bidding: `bid` (placed by the bidder or for them by phone), `max` (the leader
 * raised their maximum: not shown in the history) or `auto` (their proxy answered). `max_amount` is
 * private to its bidder and the admins.
 */
class AuctionBid extends Model
{
    public const KINDS = ['bid', 'max', 'auto'];

    protected $guarded = ['id'];

    protected $casts = [
        'voided_at' => 'datetime',
        'amount' => 'decimal:3',
        'max_amount' => 'decimal:3',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(AuctionLot::class, 'auction_lot_id');
    }

    public function bidder(): BelongsTo
    {
        return $this->belongsTo(AuctionBidder::class, 'auction_bidder_id');
    }

    public function placedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by_user_id');
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    /** What the bid history shows: placed and proxy bids, not maximum raises. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->valid()->where('kind', '!=', 'max');
    }
}
