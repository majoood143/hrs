<?php

namespace App\Models;

use App\Services\Auctions\Money;
use App\Services\Auctions\ProxyBidding;
use App\Services\Orders\OrderPricing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One horse in an auction. Bids are written only by Services\Auctions\PlaceBid (and VoidBid), the
 * close only by CloseLot; `current_price`, `leading_bid_id` and the counts are their cache.
 */
class AuctionLot extends Model
{
    /** draft (not in the catalogue), ready (in the catalogue, open for bids in its window), then sold / unsold, or withdrawn */
    public const STATUSES = ['draft', 'ready', 'sold', 'unsold', 'withdrawn'];

    public const GENDERS = ['stallion', 'mare', 'gelding', 'colt', 'filly'];

    /** 4 generations behind the sire and dam, in reading order (s = sire, d = dam): 28 ancestors. */
    public const PEDIGREE_KEYS = [
        'ss', 'sd', 'ds', 'dd',
        'sss', 'ssd', 'sds', 'sdd', 'dss', 'dsd', 'dds', 'ddd',
        'ssss', 'sssd', 'ssds', 'ssdd', 'sdss', 'sdsd', 'sdds', 'sddd',
        'dsss', 'dssd', 'dsds', 'dsdd', 'ddss', 'ddsd', 'ddds', 'dddd',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'born_on' => 'date',
        'starting_price' => 'decimal:3',
        'reserve_price' => 'decimal:3',
        'current_price' => 'decimal:3',
        'hammer_price' => 'decimal:3',
        'vat_rate' => 'decimal:2',
        'ends_at' => 'datetime',
        'closed_at' => 'datetime',
        'payment_due_at' => 'datetime',
        'invoice_reminded_at' => 'datetime',
        'payment_overdue_at' => 'datetime',
        'handed_over_at' => 'datetime',
        'payout_paid_at' => 'datetime',
        'payout_amount' => 'decimal:3',
        'pedigree' => 'array',
        'photos' => 'array',
        'videos' => 'array',
        'documents' => 'array',
        'hide_result_price' => 'boolean',
        'lot_number' => 'integer',
        'bids_count' => 'integer',
        'bidders_count' => 'integer',
    ];

    protected static function booted(): void
    {
        // lot n closes (n - 1) gaps after the first; a lot with bids keeps its (maybe extended) time
        static::saving(function (AuctionLot $lot) {
            $auction = $lot->auction;
            if ((int) $lot->bids_count === 0 && $auction?->ends_at) {
                $lot->ends_at = $auction->ends_at->copy()->addSeconds(max(0, $lot->lot_number - 1) * $auction->lot_gap_seconds);
            }
        });
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(AuctionBid::class);
    }

    public function watches(): HasMany
    {
        return $this->hasMany(AuctionWatch::class);
    }

    public function leadingBid(): BelongsTo
    {
        return $this->belongsTo(AuctionBid::class, 'leading_bid_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(AuctionBidder::class, 'winner_bidder_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'invoice_order_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'seller_customer_id');
    }

    /** Open for bids: in the catalogue of a published auction, and between its bidding start and its close. */
    public function isOpenAt(Carbon $now): bool
    {
        $auction = $this->auction;

        return $this->status === 'ready'
            && $auction?->status === 'published'
            && $auction->bidding_starts_at !== null && $now->gte($auction->bidding_starts_at)
            && $this->ends_at !== null && $now->lt($this->ends_at);
    }

    /**
     * The price, the leader and the leader's maximum, in baisa (from `current_price` and the leading bid).
     *
     * @return array{price: ?int, leader: ?int, leader_max: ?int}
     */
    public function biddingState(): array
    {
        $leading = $this->leadingBid;

        return [
            'price' => $this->current_price === null ? null : Money::baisa($this->current_price),
            'leader' => $leading ? (int) $leading->auction_bidder_id : null,
            'leader_max' => $leading ? Money::baisa($leading->max_amount) : null,
        ];
    }

    /** The lowest bid anyone but the leader may place now, in baisa. */
    public function minimumNextBid(): int
    {
        return ProxyBidding::minimumNext($this->biddingState(), Money::baisa($this->starting_price), $this->auction->steps());
    }

    /**
     * What the buyer pays for a hammer price, in baisa: the hammer, the auction's buyer's premium,
     * this lot's VAT on the hammer and the site's VAT on the premium (our fee).
     *
     * @return array{hammer: int, premium: int, vat_hammer: int, vat_premium: int, vat: int, total: int}
     */
    public function buyerCosts(int $hammer): array
    {
        $premium = (int) round($hammer * (float) $this->auction->buyer_premium_percent / 100);
        $vatHammer = (int) round($hammer * (float) $this->vat_rate / 100);
        $vatPremium = (int) round($premium * app(OrderPricing::class)->vatRate() / 100);

        return [
            'hammer' => $hammer,
            'premium' => $premium,
            'vat_hammer' => $vatHammer,
            'vat_premium' => $vatPremium,
            'vat' => $vatHammer + $vatPremium,
            'total' => $hammer + $premium + $vatHammer + $vatPremium,
        ];
    }

    /** Taken from the seller's share of a sale: the auction's commission on the hammer plus its entry fee (baisa). */
    public function sellerCommission(int $hammer): int
    {
        return (int) round($hammer * (float) $this->auction->seller_commission_percent / 100) + Money::baisa($this->auction->entry_fee);
    }

    /** null while nobody bid or there is no reserve */
    public function reserveMet(): ?bool
    {
        if ($this->reserve_price === null || $this->current_price === null) {
            return null;
        }

        return Money::baisa($this->current_price) >= Money::baisa($this->reserve_price);
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return collect(self::STATUSES)->mapWithKeys(fn (string $s) => [$s => __('auctions.lot_statuses.'.$s)])->all();
    }

    /** @return array<string, string> */
    public static function genderOptions(): array
    {
        return collect(self::GENDERS)->mapWithKeys(fn (string $g) => [$g => __('auctions.genders.'.$g)])->all();
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }

    public function getTaglineAttribute(): ?string
    {
        return (app()->getLocale() === 'ar' ? $this->ar_tagline : $this->en_tagline) ?: null;
    }

    public function getSireAttribute(): ?string
    {
        return (app()->getLocale() === 'ar' ? $this->sire_ar : null) ?: $this->sire_en;
    }

    public function getDamAttribute(): ?string
    {
        return (app()->getLocale() === 'ar' ? $this->dam_ar : null) ?: $this->dam_en;
    }

    public function getDamLineAttribute(): ?string
    {
        return (app()->getLocale() === 'ar' ? $this->ar_dam_line : $this->en_dam_line) ?: null;
    }

    public function getNoteAttribute(): ?string
    {
        return (app()->getLocale() === 'ar' ? $this->ar_note : $this->en_note) ?: null;
    }

    /** @return array<int, string> public URLs */
    public function photoUrls(): array
    {
        return collect($this->photos ?? [])->map(fn (string $path) => Storage::disk('public')->url($path))->all();
    }

    public function url(): string
    {
        return route('auctions.lot', ['auction' => $this->auction->slug, 'lot' => $this->lot_number]);
    }
}
