<?php

namespace Tests\Feature\Games;

use App\Filament\Resources\GameAttemptResource\Pages\ListGameAttempts;
use App\Filament\Resources\GameAttemptResource\Pages\ViewGameAttempt;
use App\Filament\Resources\GameCampaignResource\Pages\CreateGameCampaign;
use App\Filament\Resources\GameCampaignResource\Pages\EditGameCampaign;
use App\Filament\Resources\GameCampaignResource\Pages\ListGameCampaigns;
use App\Filament\Resources\GameCampaignResource\RelationManagers\AttemptsRelationManager;
use App\Filament\Resources\GameCampaignResource\RelationManagers\AwardsRelationManager;
use App\Filament\Resources\PromoCodeResource\Pages\CreatePromoCode;
use App\Filament\Resources\PromoCodeResource\Pages\EditPromoCode;
use App\Filament\Resources\PromoCodeResource\Pages\ListPromoCodes;
use App\Models\GameAttempt;
use App\Models\GameAward;
use App\Models\GameCampaign;
use App\Models\PromoCode;
use App\Services\Games\LeaderboardService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\MakesGames;
use Tests\TestCase;

/** Admin → Games: the screens in both languages, a campaign with prizes, the review of races and giving the prizes. */
class GameAdminTest extends TestCase
{
    use MakesGames;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareGames();
        $this->useSms('demo');
        $this->withoutVite();
        Storage::fake('public');

        Gate::before(fn () => true);
        $this->actingAsAdmin();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /** The admin on the panel's guard (a race in the test switched the default guard to the customer's). */
    private function actingAsAdmin(): void
    {
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        }, 'web');
        Auth::shouldUse('web');
    }

    public function test_promo_codes_are_made_and_listed_in_both_languages(): void
    {
        Livewire::test(CreatePromoCode::class)
            ->fillForm([
                'code' => 'eid25', 'name' => 'Eid offer', 'discount_type' => 'percentage', 'discount_value' => 25,
                'max_discount_amount' => 10, 'max_uses' => 50, 'is_active' => true, 'restricted_mobile' => '9123 4567',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $code = PromoCode::query()->sole();
        $this->assertSame(['EID25', '96891234567', '25%'], [$code->code, $code->restricted_mobile, $code->label()]);

        foreach (['en', 'ar'] as $locale) {
            App::setLocale($locale);
            Livewire::test(ListPromoCodes::class)->assertOk()->assertSee('EID25');
            Livewire::test(EditPromoCode::class, ['record' => $code->getKey()])->assertOk();
        }
    }

    public function test_a_campaign_is_created_with_its_race_and_prizes(): void
    {
        Livewire::test(CreateGameCampaign::class)
            ->fillForm([
                'en_name' => 'Spring Race', 'ar_name' => 'سباق الربيع', 'slug' => 'spring-race', 'status' => 'published',
                'starts_at' => '2027-04-01 18:00', 'ends_at' => '2027-04-10 18:00',
                'race_id' => 'sultans-cup', 'difficulty' => 'expert', 'risk_threshold' => 60, 'attempts_per_day' => 5, 'leaderboard_size' => 10,
                'prizes' => [
                    ['rank' => 1, 'en_title' => 'A riding lesson', 'ar_title' => 'حصة ركوب'],
                    ['rank' => 2, 'en_title' => 'A stable tour', 'ar_title' => 'جولة'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $campaign = GameCampaign::query()->where('slug', 'spring-race')->sole();
        $this->assertSame('sultans-cup', $campaign->race_id);
        $this->assertSame([1, 2], $campaign->prizes->pluck('rank')->all());

        Livewire::test(CreateGameCampaign::class)
            ->fillForm(['en_name' => 'X', 'ar_name' => 'س', 'slug' => 'bad', 'starts_at' => '2027-04-10 18:00', 'ends_at' => '2027-04-01 18:00', 'race_id' => 'rahba-sprint', 'difficulty' => 'normal'])
            ->call('create')
            ->assertHasFormErrors(['ends_at']);
    }

    public function test_every_screen_opens_in_both_languages(): void
    {
        $campaign = $this->campaign();
        $campaign->prizes()->create(['rank' => 1, 'en_title' => 'A riding lesson', 'ar_title' => 'حصة ركوب']);
        $this->race($this->player(), $campaign)->assertOk();
        $attempt = GameAttempt::query()->sole();
        $this->actingAsAdmin();

        foreach (['en', 'ar'] as $locale) {
            App::setLocale($locale);
            Livewire::test(ListGameCampaigns::class)->assertOk()->assertCanSeeTableRecords([$campaign]);
            Livewire::test(EditGameCampaign::class, ['record' => $campaign->getRouteKey()])->assertOk();
            Livewire::test(AttemptsRelationManager::class, ['ownerRecord' => $campaign, 'pageClass' => EditGameCampaign::class])->assertOk()->assertCanSeeTableRecords([$attempt]);
            Livewire::test(AwardsRelationManager::class, ['ownerRecord' => $campaign, 'pageClass' => EditGameCampaign::class])->assertOk();
            Livewire::test(ListGameAttempts::class)->assertOk()->assertCanSeeTableRecords([$attempt]);
            Livewire::test(ViewGameAttempt::class, ['record' => $attempt->getRouteKey()])->assertOk()->assertSee($locale === 'ar' ? 'المركز' : 'Place');
        }

        Livewire::test(EditGameCampaign::class, ['record' => $campaign->getRouteKey()])->mountAction('leaderboard')->assertActionMounted('leaderboard');
        $modal = view('filament.games.leaderboard', [
            'rows' => app(LeaderboardService::class)->top($campaign, 10),
            'prizes' => $campaign->prizes()->get()->keyBy('rank'),
        ])->render();
        $this->assertStringContainsString('Salim Al Harthy', $modal);
        $this->assertStringContainsString('+96891234567', $modal);
        $this->assertStringContainsString('1:06.40', $modal);
        $this->assertStringContainsString('حصة ركوب', $modal); // the prize, in the admin's language (Arabic last)
    }

    public function test_a_flagged_race_is_cleared_or_thrown_out_and_a_cheat_is_blocked(): void
    {
        $campaign = $this->campaign();
        $player = $this->player();
        $this->race($player, $campaign, [], seconds: 30)->assertJsonPath('under_review', true);
        $attempt = GameAttempt::query()->sole();
        $this->actingAsAdmin();

        Livewire::test(ListGameAttempts::class)->callTableAction('clear', $attempt, ['note' => 'Checked with the player']);
        $this->assertSame(GameAttempt::REVIEW_CLEARED, $attempt->fresh()->review_status);
        $this->getJson(route('games.leaderboard', 'ramadan-race'))->assertJsonPath('players', 1);

        Livewire::test(ListGameAttempts::class)->callTableAction('invalidate', $attempt);
        $this->assertSame(GameAttempt::REVIEW_INVALIDATED, $attempt->fresh()->review_status);
        $this->getJson(route('games.leaderboard', 'ramadan-race'))->assertJsonPath('players', 0);

        Livewire::test(ListGameAttempts::class)->callTableAction('clear', $attempt);
        Livewire::test(ViewGameAttempt::class, ['record' => $attempt->getRouteKey()])->callAction('block', ['reason' => 'Two accounts']);
        $this->assertNotNull($player->fresh()->games_blocked_at);
        $this->getJson(route('games.leaderboard', 'ramadan-race'))->assertJsonPath('players', 0);

        Livewire::test(ViewGameAttempt::class, ['record' => $attempt->getRouteKey()])->callAction('unblock');
        $this->assertNull($player->fresh()->games_blocked_at);
    }

    public function test_the_prizes_are_given_from_the_edit_page_only_once_it_may(): void
    {
        $campaign = $this->campaign();
        $campaign->prizes()->create(['rank' => 1, 'en_title' => 'A riding lesson', 'ar_title' => 'حصة ركوب']);
        $this->race($this->player(), $campaign)->assertOk();
        $this->actingAsAdmin();

        Livewire::test(EditGameCampaign::class, ['record' => $campaign->getRouteKey()])->callAction('finalize');
        $this->assertSame(0, GameAward::query()->count()); // not ended yet

        $this->travelTo($campaign->ends_at->copy()->addMinute());
        Livewire::test(EditGameCampaign::class, ['record' => $campaign->getRouteKey()])->callAction('finalize')->assertNotified();
        $this->assertSame(GameAward::STATUS_NOTIFIED, GameAward::query()->sole()->status);

        Livewire::test(AwardsRelationManager::class, ['ownerRecord' => $campaign->fresh(), 'pageClass' => EditGameCampaign::class])
            ->callTableAction('void', GameAward::query()->sole());
        $this->assertSame(GameAward::STATUS_VOID, GameAward::query()->sole()->status);

        // a campaign that was played cannot be deleted
        Livewire::test(EditGameCampaign::class, ['record' => $campaign->getRouteKey()])->assertActionHidden('delete');
    }
}
