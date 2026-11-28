<?php

namespace Tests\Feature\Games;

use App\Models\GameAttempt;
use App\Models\GameAward;
use App\Models\NotificationLog;
use App\Services\Games\GameAwardService;
use App\Services\Games\GameException;
use Tests\Concerns\MakesGames;
use Tests\TestCase;

/** Closing a campaign: who wins which prize, when it may be done, and the winners' messages. */
class GameAwardTest extends TestCase
{
    use MakesGames;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareGames();
        $this->useSms('demo');
    }

    public function test_the_prize_ranks_of_the_live_board_win_and_are_told_by_sms_in_their_language(): void
    {
        $campaign = $this->campaign(['leaderboard_freeze_at' => $this->opensAt->copy()->addHours(2)]);
        $campaign->prizes()->createMany([
            ['rank' => 1, 'en_title' => 'A riding lesson', 'ar_title' => 'حصة ركوب'],
            ['rank' => 2, 'en_title' => 'A stable tour', 'ar_title' => 'جولة في الإسطبل'],
        ]);
        $first = $this->player('96890000001', 'Salim Al Harthy');
        $first->update(['locale' => 'ar']);
        $second = $this->player('96890000002', 'Aisha');
        $third = $this->player('96890000003', 'Maryam');

        $this->race($second, $campaign, ['position' => 2])->assertOk();
        $this->race($third, $campaign, ['position' => 5])->assertOk();
        // after the public board froze: still counts for the prizes
        $this->travel(3)->hours();
        $this->race($first, $campaign, ['position' => 1])->assertOk();

        $this->travelTo($campaign->ends_at->copy()->addMinute());
        $awards = app(GameAwardService::class)->finalize($campaign);

        $this->assertCount(2, $awards);
        $this->assertSame([$first->id, $second->id], array_map(fn (GameAward $a) => $a->customer_id, $awards));
        $this->assertSame([GameAward::STATUS_NOTIFIED, GameAward::STATUS_NOTIFIED], array_map(fn (GameAward $a) => $a->fresh()->status, $awards));
        $this->assertNotNull($campaign->fresh()->awarded_at);

        $sms = NotificationLog::query()->where('type', 'game_award')->orderBy('id')->get();
        $this->assertSame(['96890000001', '96890000002'], $sms->pluck('recipient')->all());
        $this->assertStringContainsString('مبروك Salim', $sms[0]->message); // in the winner's language
        $this->assertStringContainsString('حصة ركوب', $sms[0]->message);
        $this->assertStringContainsString('Aisha', $sms[1]->message);
        $this->assertStringContainsString('A stable tour', $sms[1]->message);

        $this->expectException(GameException::class);
        app(GameAwardService::class)->finalize($campaign->fresh());
    }

    public function test_a_prize_with_a_discount_gives_the_winner_a_single_use_code_for_their_mobile(): void
    {
        $campaign = $this->campaign();
        $campaign->prizes()->createMany([
            ['rank' => 1, 'en_title' => '50% off a lesson', 'ar_title' => 'خصم 50% على حصة', 'discount_type' => 'percentage', 'discount_value' => 50, 'max_discount_amount' => 20, 'valid_days' => 30],
            ['rank' => 2, 'en_title' => 'A stable tour', 'ar_title' => 'جولة في الإسطبل'],
        ]);
        $this->race($this->player('96890000001', 'Salim'), $campaign, ['position' => 1])->assertOk();
        $this->race($this->player('96890000002', 'Aisha'), $campaign, ['position' => 2])->assertOk();

        $this->travelTo($campaign->ends_at->copy()->addMinute());
        [$first, $second] = app(GameAwardService::class)->finalize($campaign);

        $code = $first->fresh()->promoCode;
        $this->assertNotNull($code);
        $this->assertSame(['96890000001', 1, 'game', 'percentage', '50.000', '20.000'], [$code->restricted_mobile, $code->max_uses, $code->source, $code->discount_type, $code->discount_value, $code->max_discount_amount]);
        $this->assertTrue($code->valid_until->isSameDay(now()->addDays(30)));
        $this->assertNull($second->fresh()->promo_code_id);

        $sms = NotificationLog::query()->where('type', 'game_award')->orderBy('id')->pluck('message');
        $this->assertStringContainsString($code->code, $sms[0]);
        $this->assertStringContainsString('hand it over', $sms[1]); // a text prize keeps its message

        app(GameAwardService::class)->void($first->fresh());
        $this->assertFalse($code->fresh()->is_active);
    }

    public function test_it_waits_for_the_end_the_prizes_and_the_reviews_that_could_win(): void
    {
        $campaign = $this->campaign();
        $service = app(GameAwardService::class);
        $this->assertSame(__('games.award.not_ended'), $service->blocker($campaign));

        $this->travelTo($campaign->ends_at->copy()->addMinute());
        $this->assertSame(__('games.award.no_prizes'), $service->blocker($campaign));

        $campaign->prizes()->create(['rank' => 1, 'en_title' => 'A riding lesson', 'ar_title' => 'حصة ركوب']);
        $campaign->update(['ends_at' => now()->addDay()]);
        $this->race($this->player('96890000001'), $campaign, ['position' => 3])->assertOk();
        // a better race that only looks too fast
        $this->race($this->player('96890000002'), $campaign, ['position' => 1], seconds: 30)->assertJsonPath('under_review', true);
        $this->travelTo($campaign->fresh()->ends_at->copy()->addMinute());

        $this->assertStringContainsString('review', (string) $service->blocker($campaign->fresh()));

        GameAttempt::query()->where('review_status', GameAttempt::REVIEW_FLAGGED)->update(['review_status' => GameAttempt::REVIEW_CLEARED]);
        $this->assertNull($service->blocker($campaign->fresh()));
        $awards = $service->finalize($campaign->fresh());
        $this->assertSame('96890000002', $awards[0]->customer->phone);

        $service->void($awards[0]);
        $this->assertSame(GameAward::STATUS_VOID, $awards[0]->fresh()->status);
        // a prize taken back is never sent again
        $this->assertSame(GameAward::STATUS_VOID, $service->notify($awards[0]->fresh())->status);
    }

    public function test_without_any_channel_the_award_keeps_why_it_was_not_sent(): void
    {
        $this->useSms('');
        $campaign = $this->campaign();
        $campaign->prizes()->create(['rank' => 1, 'en_title' => 'A riding lesson', 'ar_title' => 'حصة ركوب']);
        $this->race($this->player(), $campaign)->assertOk();
        $this->travelTo($campaign->ends_at->copy()->addMinute());

        $award = app(GameAwardService::class)->finalize($campaign)[0]->fresh();

        $this->assertSame(GameAward::STATUS_AWARDED, $award->status);
        $this->assertSame(__('games.award.no_channel'), $award->notify_error);
    }
}
