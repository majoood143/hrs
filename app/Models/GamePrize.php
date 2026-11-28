<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What a rank wins in a campaign (e.g. "1st: a free riding lesson"), handed over by the organisers. */
class GamePrize extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rank' => 'integer', 'discount_value' => 'decimal:3', 'max_discount_amount' => 'decimal:3', 'valid_days' => 'integer'];
    }

    /** A prize with a discount gives its winner a single-use promo code bound to their mobile. */
    public function givesPromoCode(): bool
    {
        return filled($this->discount_type) && (float) $this->discount_value > 0;
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(GameCampaign::class, 'game_campaign_id');
    }

    public function getTitleAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_title : $this->en_title;
    }
}
