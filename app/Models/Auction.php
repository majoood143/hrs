<?php

namespace App\Models;

use App\Filament\RichEditor\TextDirectionPlugin;
use App\Models\Concerns\HasRichDescription;
use App\Services\Auctions\BidIncrements;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * An online auction (admin → Auctions): a catalogue of lots that goes online at `preview_at`, opens
 * for bids at `bidding_starts_at` and closes lot by lot from `ends_at` (docs/horse-auctions-plan.md).
 */
class Auction extends Model
{
    public const STATUSES = ['draft', 'published', 'cancelled'];

    /** OMR; used when the auction has no table of its own. */
    public const DEFAULT_INCREMENTS = [
        ['from' => 0, 'step' => 10],
        ['from' => 1000, 'step' => 50],
        ['from' => 5000, 'step' => 100],
        ['from' => 10000, 'step' => 250],
        ['from' => 20000, 'step' => 500],
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'preview_at' => 'datetime',
        'bidding_starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'increments' => 'array',
        'deposit_amount' => 'decimal:3',
        'entry_fee' => 'decimal:3',
        'buyer_premium_percent' => 'decimal:2',
        'seller_commission_percent' => 'decimal:2',
        'lot_gap_seconds' => 'integer',
        'extension_minutes' => 'integer',
        'payment_days' => 'integer',
        'auto_approve' => 'boolean',
    ];

    protected static function booted(): void
    {
        // a changed close time or gap moves every lot nobody has bid on yet
        static::saved(function (Auction $auction) {
            if ($auction->wasChanged(['ends_at', 'lot_gap_seconds'])) {
                $auction->lots()->where('bids_count', 0)->get()->each->save();
            }
        });
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(AuctionOrganizer::class, 'organizer_id');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(AuctionLot::class)->orderBy('lot_number');
    }

    public function bidders(): HasMany
    {
        return $this->hasMany(AuctionBidder::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /** On the website: published and its catalogue online. */
    public function scopeOnline(Builder $query): Builder
    {
        return $query->published()->where('preview_at', '<=', now());
    }

    /** The lots the website shows (not drafts). */
    public function publicLots(): HasMany
    {
        return $this->lots()->whereIn('status', ['ready', 'sold', 'unsold', 'withdrawn']);
    }

    /** preview (catalogue only), live (taking bids), ended (every lot closed) */
    public function phase(): string
    {
        if ($this->bidding_starts_at === null || now()->lt($this->bidding_starts_at)) {
            return 'preview';
        }

        return $this->lots()->where('status', 'ready')->exists() ? 'live' : 'ended';
    }

    public function getDescriptionAttribute(): ?string
    {
        return (app()->getLocale() === 'ar' ? $this->ar_description : $this->en_description) ?: null;
    }

    public function getPaymentNoteAttribute(): ?string
    {
        return (app()->getLocale() === 'ar' ? $this->ar_payment_note : $this->en_payment_note) ?: null;
    }

    /** The conditions in the current language as safe HTML (rich text from the admin editor, sanitized). */
    public function conditionsHtml(): ?string
    {
        $html = HasRichDescription::descriptionAsHtml(app()->getLocale() === 'ar' ? $this->ar_conditions : $this->en_conditions);

        return $html === null ? null : RichContentRenderer::make($html)->plugins([TextDirectionPlugin::make()])->toHtml();
    }

    public function steps(): BidIncrements
    {
        return new BidIncrements($this->increments ?: self::DEFAULT_INCREMENTS);
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return collect(self::STATUSES)->mapWithKeys(fn (string $s) => [$s => __('auctions.statuses.'.$s)])->all();
    }

    public function getTitleAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_title : $this->en_title;
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_photo ? Storage::disk('public')->url($this->cover_photo) : null;
    }
}
