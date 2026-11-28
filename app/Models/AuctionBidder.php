<?php

namespace App\Models;

use App\Services\Auctions\AuctionNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A customer registered to bid in one auction; others see only the paddle number. */
class AuctionBidder extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected', 'blocked'];

    protected $guarded = ['id'];

    protected $casts = [
        'approved_at' => 'datetime',
        'accepted_terms_at' => 'datetime',
        'paddle_number' => 'integer',
        'deposit_kept_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AuctionBidder $bidder) {
            $bidder->paddle_number ??= (int) static::query()->where('auction_id', $bidder->auction_id)->max('paddle_number') + 1;
        });

        // approved by an admin, by the deposit or at once: they are told they can bid
        static::saved(function (AuctionBidder $bidder) {
            if ($bidder->status === 'approved' && ($bidder->wasRecentlyCreated || $bidder->wasChanged('status'))) {
                app(AuctionNotifier::class)->approved($bidder);
            }
        });
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function depositOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'deposit_order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(AuctionBid::class);
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return collect(self::STATUSES)->mapWithKeys(fn (string $s) => [$s => __('auctions.bidder_statuses.'.$s)])->all();
    }

    public function label(): string
    {
        return __('auctions.paddle', ['number' => $this->paddle_number]);
    }
}
