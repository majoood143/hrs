<?php

namespace App\Services\Games;

use App\Models\Customer;
use App\Models\GameAttempt;
use App\Models\GameAward;
use App\Models\GameCampaign;
use App\Models\GamePrize;
use App\Models\MobileDevice;
use App\Models\PromoCode;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Push\ExpoPush;
use App\Services\Sms\SmsManager;
use App\Support\Locale;
use App\Support\NotificationText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Closes a campaign: the customers at the prize ranks of the live board (never a frozen public one)
 * get an award, then an SMS and a push saying what they won. Prizes are handed over by the
 * organisers, so the award is the record of who is owed what; a prize with a discount also makes a
 * single-use promo code bound to the winner's mobile (stable sessions and lesson packages).
 */
class GameAwardService
{
    public function __construct(
        private readonly LeaderboardService $leaderboard,
        private readonly SmsManager $sms,
        private readonly ExpoPush $push,
    ) {}

    /** Why the campaign cannot be finalized yet, or null when it can. */
    public function blocker(GameCampaign $campaign): ?string
    {
        if ($campaign->isAwarded()) {
            return __('games.award.already_awarded');
        }

        if (! $campaign->hasEnded()) {
            return __('games.award.not_ended');
        }

        if ($campaign->prizes()->count() === 0) {
            return __('games.award.no_prizes');
        }

        $pending = $this->pendingReviewThatCouldPlace($campaign);

        return $pending > 0 ? trans_choice('games.award.pending_reviews', $pending, ['count' => $pending]) : null;
    }

    /** @return list<GameAward> the awards made, each already notified where possible */
    public function finalize(GameCampaign $campaign, ?User $by = null): array
    {
        if ($reason = $this->blocker($campaign)) {
            throw new GameException('cannot_award', $reason);
        }

        $prizes = $campaign->prizes()->get()->keyBy('rank');
        $rows = $this->leaderboard->top($campaign, (int) $prizes->keys()->max());

        $awards = DB::transaction(function () use ($campaign, $prizes, $rows, $by) {
            $made = [];

            foreach ($rows as $row) {
                /** @var GamePrize|null $prize */
                if ($prize = $prizes->get($row['rank'])) {
                    $phone = Customer::query()->whereKey($row['customer_id'])->value('phone');

                    $made[] = GameAward::query()->create([
                        'game_campaign_id' => $campaign->id,
                        'customer_id' => $row['customer_id'],
                        'game_attempt_id' => $row['attempt_id'],
                        'game_prize_id' => $prize->id,
                        'promo_code_id' => $prize->givesPromoCode() && $phone ? $this->promoCode($campaign, $prize, $phone)->id : null,
                        'rank' => $row['rank'],
                        'status' => GameAward::STATUS_AWARDED,
                    ]);
                }
            }

            $campaign->forceFill(['awarded_at' => now(), 'awarded_by' => $by?->getKey()])->save();

            return $made;
        });

        foreach ($awards as $award) {
            $this->notify($award);
        }

        return $awards;
    }

    /** Tell (or tell again) the winner. Never throws: the outcome is kept on the award. */
    public function notify(GameAward $award): GameAward
    {
        $award->loadMissing(['customer', 'campaign', 'prize', 'promoCode']);

        if ($award->status === GameAward::STATUS_VOID || ! $award->customer) {
            return $award;
        }

        try {
            $customer = $award->customer;
            $locale = $customer->locale ?: 'en';
            $text = fn () => NotificationText::get($award->promoCode ? 'sms.game_award_code' : 'sms.game_award', $this->replacements($award, $customer));
            $sent = false;
            $error = null;

            if ($customer->phone && $this->sms->canSend()) {
                $result = $this->sms->send($customer->phone, Locale::within($locale, $text), 'game_award');
                $sent = $result->ok;
                $error = $result->error;
            }

            $devices = MobileDevice::query()->customerSignedIn($customer->id)->get();
            if ($devices->isNotEmpty() && $this->push->send($devices, fn () => ['title' => __('games.push.award_title'), 'body' => $text()]) !== []) {
                $sent = true;
            }

            $award->forceFill($sent
                ? ['status' => GameAward::STATUS_NOTIFIED, 'notified_at' => now(), 'notify_error' => null]
                : ['notify_error' => Str::limit((string) ($error ?? __('games.award.no_channel')), 250)]
            )->save();
        } catch (Throwable $e) {
            Log::error('Could not tell a game winner', ['award' => $award->id, 'error' => $e->getMessage()]);
            $award->forceFill(['notify_error' => Str::limit($e->getMessage(), 250)])->save();
        }

        return $award;
    }

    /** Take a prize back (the winner cheated, or cannot be reached). */
    public function void(GameAward $award): void
    {
        $award->update(['status' => GameAward::STATUS_VOID]);
        $award->promoCode?->update(['is_active' => false]);
    }

    private function promoCode(GameCampaign $campaign, GamePrize $prize, string $phone): PromoCode
    {
        do {
            $code = 'WIN'.Str::upper(Str::random(7));
        } while (PromoCode::withTrashed()->where('code', $code)->exists());

        return PromoCode::query()->create([
            'code' => $code,
            'name' => Str::limit($campaign->en_name.' #'.$prize->rank, 250, ''),
            'discount_type' => $prize->discount_type,
            'discount_value' => $prize->discount_value,
            'max_discount_amount' => $prize->discount_type === PromoCode::PERCENTAGE ? $prize->max_discount_amount : null,
            'max_uses' => 1,
            'valid_from' => now(),
            'valid_until' => $prize->valid_days ? now()->addDays($prize->valid_days)->endOfDay() : null,
            'is_active' => true,
            'restricted_mobile' => $phone,
            'source' => 'game',
        ]);
    }

    /** @return array<string, string> */
    private function replacements(GameAward $award, Customer $customer): array
    {
        return [
            'site' => SiteSetting::siteName(),
            'name' => trim(explode(' ', trim((string) $customer->name))[0] ?? ''),
            'rank' => (string) $award->rank,
            'campaign' => $award->campaign->name,
            'prize' => (string) $award->prize?->title,
            'code' => (string) $award->promoCode?->code,
            'until' => $award->promoCode?->valid_until ? $award->promoCode->valid_until->locale(app()->getLocale())->translatedFormat('j F Y') : __('promo_codes.admin.no_expiry'),
        ];
    }

    /**
     * Races waiting for review that could reach a prize rank: finalizing before they are looked at
     * could skip someone who really won (or reward a cheat after a clear).
     */
    private function pendingReviewThatCouldPlace(GameCampaign $campaign): int
    {
        $lastPrizeRank = (int) $campaign->prizes()->max('rank');
        $rows = $this->leaderboard->top($campaign, $lastPrizeRank);
        $cut = count($rows) < $lastPrizeRank ? null : end($rows);

        return GameAttempt::query()
            ->where('game_campaign_id', $campaign->id)
            ->where('status', GameAttempt::STATUS_COMPLETED)
            ->where('review_status', GameAttempt::REVIEW_FLAGGED)
            ->when($cut, fn ($q) => $q->where('score', '>=', $cut['score']))
            ->count();
    }
}
