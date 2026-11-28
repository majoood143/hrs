<?php

namespace Tests\Feature\Games;

use App\Models\GameAttempt;
use App\Services\Games\RiskScorer;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\MakesGames;
use Tests\TestCase;

/** The game's calls on the website: who may race, the limits, the server's checks and the leaderboard. */
class GameRaceTest extends TestCase
{
    use MakesGames;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareGames();
    }

    public function test_a_guest_reads_the_campaign_but_must_sign_in_to_race(): void
    {
        $campaign = $this->campaign();
        $campaign->prizes()->create(['rank' => 1, 'en_title' => 'A riding lesson', 'ar_title' => 'حصة ركوب']);

        $this->getJson(route('games.config', 'ramadan-race'))
            ->assertOk()
            ->assertJsonPath('player', null)
            ->assertJsonPath('campaign.race_id', 'rahba-sprint')
            ->assertJsonPath('campaign.phase', 'live')
            ->assertJsonPath('campaign.prizes.0.title', 'A riding lesson')
            ->assertJsonStructure(['csrf', 'urls' => ['start', 'finish', 'leaderboard', 'sign_in']]);

        $this->getJson(route('games.config', 'ramadan-race').'?lang=ar')->assertJsonPath('campaign.name', 'تحدي سباق رمضان');

        // the game's screens take General Settings' branding: the primary colour's shades and the site's fonts
        $this->useSms('demo', ['primary_color' => '#05602b', 'body_font' => 'cairo']);
        $this->getJson(route('games.config', 'ramadan-race'))
            ->assertJsonPath('brand.shades.600', '#05602b')
            ->assertJsonPath('brand.fonts.body', ['label' => 'Cairo', 'google' => 'Cairo:wght@400;600;700'])
            ->assertJsonStructure(['brand' => ['shades' => ['50', '300', '900'], 'button', 'button_text', 'fonts' => ['heading' => ['label', 'google']]]]);

        $this->postJson(route('games.start', 'ramadan-race'))->assertUnauthorized();
        $this->get(route('games.play', 'ramadan-race'))->assertRedirect(route('account.login'));

        // once signed in, "Play" goes on to the game for this campaign
        $this->actingAs($this->player(), 'customer')->get(route('games.play', 'ramadan-race'))
            ->assertRedirect($campaign->gameUrl());
        $this->assertStringContainsString('/games/horse-racing/index.html?campaign=ramadan-race', $campaign->gameUrl());
    }

    public function test_drafts_answer_only_to_admins(): void
    {
        $this->campaign(['status' => 'draft']);

        $this->getJson(route('games.config', 'ramadan-race'))->assertNotFound();
        $this->getJson(route('games.leaderboard', 'ramadan-race'))->assertNotFound();
        $this->actingAs($this->player(), 'customer')->postJson(route('games.start', 'ramadan-race'))->assertNotFound();
    }

    public function test_a_believable_race_ranks_and_the_board_masks_the_phone(): void
    {
        $campaign = $this->campaign();
        $player = $this->player();

        $this->race($player, $campaign)
            ->assertOk()
            ->assertJsonPath('position', 1)
            ->assertJsonPath('time', '1:06.40')
            ->assertJsonPath('under_review', false)
            ->assertJsonPath('is_best', true)
            ->assertJsonPath('standing.rank', 1)
            ->assertJsonPath('remaining.today', 2);

        $attempt = GameAttempt::query()->sole();
        $this->assertSame(GameAttempt::STATUS_COMPLETED, $attempt->status);
        $this->assertSame([], $attempt->flags);
        $this->assertSame('paceSetter', $attempt->horse_style);
        $this->assertGreaterThanOrEqual(75_000, $attempt->wall_ms);

        $board = $this->getJson(route('games.leaderboard', 'ramadan-race'))->assertOk();
        $board->assertJsonPath('rows.0.player', 'Salim · ••••4567')->assertJsonPath('rows.0.time', '1:06.40')->assertJsonPath('players', 1);
        $this->assertStringNotContainsString('91234567', $board->getContent());
    }

    public function test_the_best_place_ranks_first_then_the_fastest_time_and_each_player_counts_once(): void
    {
        $campaign = $this->campaign();
        $fast = $this->player('96890000001', 'Fast');
        $winner = $this->player('96890000002', 'Winner');

        $this->race($fast, $campaign, ['position' => 2, 'race_time_ms' => 65_900])->assertOk();
        $this->race($winner, $campaign, ['position' => 1, 'race_time_ms' => 67_000])->assertOk();
        $this->race($winner, $campaign, ['position' => 4, 'race_time_ms' => 70_000])->assertOk()->assertJsonPath('is_best', false);
        $this->race($fast, $campaign, ['position' => 1, 'race_time_ms' => 66_500])->assertOk()->assertJsonPath('standing.rank', 1);

        $rows = $this->getJson(route('games.leaderboard', 'ramadan-race'))->json('rows');
        $this->assertSame(['Fast · ••••0001', 'Winner · ••••0002'], array_column($rows, 'player'));
        $this->assertSame([1, 1], array_column($rows, 'position'));
    }

    public function test_the_daily_limit_counts_started_races_and_resets_on_the_muscat_day(): void
    {
        $campaign = $this->campaign(['attempts_per_day' => 2]);
        $player = $this->player();

        $this->race($player, $campaign)->assertOk();
        // started and left: still used up
        $this->actingAs($player, 'customer')->postJson(route('games.start', 'ramadan-race'))->assertOk()->assertJsonPath('remaining.today', 0);
        $this->actingAs($player, 'customer')->postJson(route('games.start', 'ramadan-race'))
            ->assertStatus(422)->assertJsonPath('code', 'no_attempts_left');

        // 20:00 UTC is midnight in Muscat
        $this->travelTo($this->opensAt->copy()->setTime(20, 0, 1));
        $this->actingAs($player, 'customer')->postJson(route('games.start', 'ramadan-race'))->assertOk();
        $this->assertSame(GameAttempt::STATUS_ABANDONED, GameAttempt::query()->orderBy('id')->get()[1]->status);
    }

    public function test_a_total_limit_holds_across_days(): void
    {
        $campaign = $this->campaign(['attempts_per_day' => null, 'attempts_total' => 1]);
        $player = $this->player();

        $this->race($player, $campaign)->assertOk()->assertJsonPath('remaining.total', 0)->assertJsonPath('remaining.today', null);
        $this->travel(2)->days();
        $this->actingAs($player, 'customer')->postJson(route('games.start', 'ramadan-race'))->assertStatus(422)->assertJsonPath('code', 'no_attempts_left');
    }

    public function test_sending_the_finish_twice_scores_the_race_once(): void
    {
        $campaign = $this->campaign();
        $player = $this->player();
        $uuid = $this->actingAs($player, 'customer')->postJson(route('games.start', 'ramadan-race'))->json('attempt');
        $this->travel(75)->seconds();

        $url = route('games.finish', ['ramadan-race', $uuid]);
        $this->actingAs($player, 'customer')->postJson($url, $this->finishData())->assertOk()->assertJsonPath('time', '1:06.40');
        $this->actingAs($player, 'customer')->postJson($url, $this->finishData(['race_time_ms' => 60_000, 'position' => 1]))
            ->assertOk()->assertJsonPath('time', '1:06.40');
    }

    public function test_a_race_faster_than_the_real_clock_waits_for_review_and_stays_off_the_board(): void
    {
        $campaign = $this->campaign();

        // 66.4 s reported, but only 40 s passed between the two calls
        $this->race($this->player(), $campaign, [], seconds: 40)
            ->assertOk()->assertJsonPath('under_review', true)->assertJsonPath('standing', null);

        $attempt = GameAttempt::query()->sole();
        $this->assertSame(['faster_than_clock'], $attempt->flags);
        $this->assertSame(GameAttempt::REVIEW_FLAGGED, $attempt->review_status);
        $this->getJson(route('games.leaderboard', 'ramadan-race'))->assertJsonPath('rows', [])->assertJsonPath('players', 0);
    }

    public function test_times_and_speeds_no_horse_can_ride_are_flagged(): void
    {
        $campaign = $this->campaign();

        // 1000 m in 50 s = 20 m/s; the game's best is about 15.2 m/s
        $this->race($this->player(), $campaign, ['race_time_ms' => 50_000, 'top_speed_kmh' => 90])->assertOk()->assertJsonPath('under_review', true);
        $this->assertEqualsCanonicalizing(['impossible_time', 'impossible_speed'], GameAttempt::query()->sole()->flags);

        $this->assertSame(['webdriver'], app(RiskScorer::class)->flags(1000, ['race_time_ms' => 66_000, 'top_speed_kmh' => 68, 'webdriver' => true], 80_000));
        $this->assertSame(60, app(RiskScorer::class)->score(['webdriver']));
    }

    public function test_a_finish_that_does_not_fit_the_race_is_refused(): void
    {
        $campaign = $this->campaign();

        $this->race($this->player(), $campaign, ['field_size' => 16])->assertStatus(422)->assertJsonValidationErrors('field_size');
        $this->race($this->player('96890000009'), $campaign, ['position' => 12])->assertStatus(422)->assertJsonValidationErrors('position');
    }

    public function test_a_player_cannot_finish_someone_elses_race(): void
    {
        $campaign = $this->campaign();
        $uuid = $this->actingAs($this->player(), 'customer')->postJson(route('games.start', 'ramadan-race'))->json('attempt');

        $this->actingAs($this->player('96890000003'), 'customer')
            ->postJson(route('games.finish', ['ramadan-race', $uuid]), $this->finishData())
            ->assertNotFound()->assertJsonPath('code', 'attempt_closed');
    }

    public function test_closed_campaigns_blocked_players_and_old_races_are_refused(): void
    {
        $this->campaign(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(3)]);
        $player = $this->player();
        $this->actingAs($player, 'customer')->postJson(route('games.start', 'ramadan-race'))->assertStatus(422)->assertJsonPath('code', 'campaign_not_open');

        $live = $this->campaign(['slug' => 'live-race']);
        $player->forceFill(['games_blocked_at' => now()])->save();
        $this->actingAs($player->fresh(), 'customer')->postJson(route('games.start', 'live-race'))->assertForbidden()->assertJsonPath('code', 'player_blocked');

        $other = $this->player('96890000004');
        $uuid = $this->actingAs($other, 'customer')->postJson(route('games.start', 'live-race'))->json('attempt');
        $this->travel(GameAttempt::ABANDON_AFTER_MINUTES + 1)->minutes();
        $this->actingAs($other, 'customer')->postJson(route('games.finish', ['live-race', $uuid]), $this->finishData())
            ->assertStatus(422)->assertJsonPath('code', 'attempt_expired');

        $this->assertNotNull($live);
    }

    public function test_the_scheduled_command_abandons_unfinished_races(): void
    {
        $this->campaign();
        $this->actingAs($this->player(), 'customer')->postJson(route('games.start', 'ramadan-race'))->assertOk();
        $this->travel(GameAttempt::ABANDON_AFTER_MINUTES + 1)->minutes();

        Artisan::call('games:expire-attempts');

        $this->assertSame(GameAttempt::STATUS_ABANDONED, GameAttempt::query()->sole()->status);
    }

    public function test_the_page_block_shows_the_board_and_play_for_a_signed_in_player_and_sign_in_for_a_guest(): void
    {
        $campaign = $this->campaign();
        $campaign->prizes()->create(['rank' => 1, 'en_title' => 'A riding lesson', 'ar_title' => 'حصة ركوب']);
        $player = $this->player();
        $this->race($player, $campaign)->assertOk();
        $this->app['auth']->guard('customer')->logout();

        $guest = view('cms.blocks.game_campaign', ['data' => ['campaign_id' => $campaign->id]])->render();
        $this->assertStringContainsString(__('games.site.sign_in'), $guest);
        $this->assertStringContainsString(route('games.play', 'ramadan-race'), $guest);
        $this->assertStringContainsString('A riding lesson', $guest);
        $this->assertStringContainsString('Salim · ••••4567', $guest);
        $this->assertStringNotContainsString('data-game-open', $guest);

        $this->actingAs($player, 'customer');
        $signedIn = view('cms.blocks.game_campaign', ['data' => ['campaign_id' => $campaign->id]])->render();
        $this->assertStringContainsString('data-game-open="'.e($campaign->gameUrl()).'"', $signedIn);
        $this->assertStringContainsString(__('games.site.races_left'), $signedIn);

        $campaign->update(['status' => 'draft']);
        $this->assertSame('', trim(view('cms.blocks.game_campaign', ['data' => ['campaign_id' => $campaign->id]])->render()));
    }
}
