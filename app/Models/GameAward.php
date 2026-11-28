<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A rank's prize given to a customer when the campaign is finalized, and whether they were told. */
class GameAward extends Model
{
    public const STATUS_AWARDED = 'awarded';

    public const STATUS_NOTIFIED = 'notified';

    public const STATUS_VOID = 'void';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rank' => 'integer', 'notified_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(GameCampaign::class, 'game_campaign_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(GameAttempt::class, 'game_attempt_id');
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(GamePrize::class, 'game_prize_id');
    }

    /** The winner's promo code, when the prize carries a discount. */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }
}
