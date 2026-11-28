<?php

namespace Tests\Feature\Games;

use App\Filament\Resources\GameCampaignResource\Pages\EditGameCampaign;
use App\Filament\Resources\GameCampaignResource\RelationManagers\RacesRelationManager;
use App\Models\GameAttempt;
use App\Models\GameRace;
use App\Services\Games\GameException;
use App\Services\Games\GameScore;
use App\Services\Games\RealRaces;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\FakesRacingSource;
use Tests\Concerns\MakesGames;
use Tests\TestCase;

/** Real races in a campaign: copied from the racing club, chosen by the player with a horse, scored in points. */
class RealRacesTest extends TestCase
{
    use FakesRacingSource;
    use MakesGames;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareGames(); // the site layout too (prepareRacingSite() would build it twice)
        config(['racing.base_url' => 'http://racing.test']);
        $this->fakeRacingSource();
    }

    private function importedRace(): GameRace
    {
        return app(RealRaces::class)->import($this->campaign(), 9178, '2021-11-19');
    }

    public function test_a_run_race_is_copied_with_its_field_in_both_languages_and_its_result(): void
    {
        $race = $this->importedRace();

        $this->assertSame(9178, $race->source_race_id);
        $this->assertGreaterThanOrEqual(800, $race->distance);
        $this->assertTrue($race->has_result);
        $this->assertNotNull($race->winning_time_ms);
        $this->assertGreaterThan(1, $race->fieldSize());
        $this->assertLessThanOrEqual(GameRace::MAX_RUNNERS, $race->fieldSize());

        $first = $race->runner('1');
        $this->assertSame('S.s.r. Alfaseeh (OM)', $first['name']['en']);
        $this->assertSame(9, $first['gate']);
        $this->assertSame(83, $first['rating']);
        $this->assertSame(['starts' => 27, 'first' => 7, 'second' => 5, 'third' => 4], $first['record']);
        $this->assertSame(6, $first['age']);
        $this->assertSame('Amur bin Ali Al Rasbi', $first['jockey']['en']);
        $this->assertSame('/img/OwnerColours/khalid mohamed falahi.jpg', $first['silks']);
        $this->assertSame(1, $race->runner('16')['result']); // Nawaya won
        $this->assertSame(1, $race->ratingRank('1')); // OR 83, the best of the field

        // read again: the same row, not a second one
        app(RealRaces::class)->import($race->campaign, 9178, '2021-11-19');
        $this->assertSame(1, GameRace::query()->count());
    }

    public function test_a_race_without_a_declared_field_is_refused(): void
    {
        $this->expectException(GameException::class);
        $this->expectExceptionMessage(__('games.errors.field_not_declared'));

        app(RealRaces::class)->import($this->campaign(), 10005, '2026-12-01');
    }

    public function test_the_source_running_time_reads_as_milliseconds(): void
    {
        $this->assertSame(80_850, RealRaces::runningTimeMs('1:20:85'));
        $this->assertSame(128_400, RealRaces::runningTimeMs('2:08.4'));
        $this->assertNull(RealRaces::runningTimeMs('—'));
    }

    public function test_points_reward_the_place_and_more_on_a_low_rated_horse(): void
    {
        $this->assertSame(100, GameScore::points(1, 1, 16));
        $this->assertSame(150, GameScore::points(1, 16, 16));
        $this->assertSame(1.25, GameScore::multiplier(5, 9));
        $this->assertSame(70, GameScore::points(2, null, 11)); // the game's own course: place points only
        $this->assertSame(0, GameScore::points(12, 16, 16));
    }

    public function test_the_player_chooses_a_race_and_horse_and_scores_points_and_sees_the_real_result_after(): void
    {
        $race = $this->importedRace();
        $player = $this->player();
        $field = $race->fieldSize();

        $config = $this->actingAs($player, 'customer')->getJson(route('games.config', 'ramadan-race'))->assertOk();
        $config->assertJsonPath('campaign.races.0.id', $race->id)->assertJsonPath('campaign.races.0.run', true);
        $runner = collect($config->json('campaign.races.0.runners'))->firstWhere('no', '16');
        $this->assertSame('Nawaya (OM)', $runner['name']);
        $this->assertStringContainsString('/racing/image?p=', $runner['silks_url']);
        $this->assertArrayNotHasKey('result', $runner); // the real result waits until after the ride
        $this->assertSame(GameScore::multiplier($race->ratingRank('16'), $field), $runner['multiplier']);

        // a campaign with real races needs both choices
        $this->actingAs($player, 'customer')->postJson(route('games.start', 'ramadan-race'))->assertStatus(422)->assertJsonPath('code', 'choose_race');
        $this->actingAs($player, 'customer')->postJson(route('games.start', 'ramadan-race'), ['race' => $race->id, 'horse' => '99'])->assertStatus(422);

        $uuid = $this->actingAs($player, 'customer')->postJson(route('games.start', 'ramadan-race'), ['race' => $race->id, 'horse' => '16'])->assertOk()->json('attempt');
        $this->travel(150)->seconds();
        $answer = $this->actingAs($player, 'customer')->postJson(route('games.finish', ['ramadan-race', $uuid]), [
            'position' => 1, 'field_size' => $field, 'race_time_ms' => (int) ($race->distance / 15 * 1000), 'top_speed_kmh' => 66,
        ])->assertOk();

        $attempt = GameAttempt::query()->sole();
        $this->assertSame($race->id, $attempt->game_race_id);
        $this->assertSame('16', $attempt->horse_no);
        $this->assertSame(GameScore::points(1, $race->ratingRank('16'), $field), $attempt->score);
        $answer->assertJsonPath('score', $attempt->score)
            ->assertJsonPath('real.horse_position', 1)
            ->assertJsonPath('real.winner', 'Nawaya (OM)')
            ->assertJsonPath('standing.score', $attempt->score);

        // the race's own field size, not the game course's
        $uuid = $this->actingAs($player, 'customer')->postJson(route('games.start', 'ramadan-race'), ['race' => $race->id, 'horse' => '1'])->json('attempt');
        $this->travel(150)->seconds();
        $this->actingAs($player, 'customer')->postJson(route('games.finish', ['ramadan-race', $uuid]), ['position' => 1, 'field_size' => 11, 'race_time_ms' => 90_000, 'top_speed_kmh' => 66])
            ->assertStatus(422)->assertJsonValidationErrors('field_size');
    }

    public function test_the_board_ranks_by_points_so_an_outsider_win_beats_a_favourite_win(): void
    {
        $race = $this->importedRace();
        $field = $race->fieldSize();
        $outsider = collect($race->runners)->sortBy(fn ($r) => $r['rating'] ?? -1)->first()['no'];
        $favourite = $this->player('96890000001', 'Favourite');
        $underdog = $this->player('96890000002', 'Underdog');

        foreach ([[$favourite, '1'], [$underdog, (string) $outsider]] as [$who, $horse]) {
            $uuid = $this->actingAs($who, 'customer')->postJson(route('games.start', 'ramadan-race'), ['race' => $race->id, 'horse' => $horse])->json('attempt');
            $this->travel(150)->seconds();
            $this->actingAs($who, 'customer')->postJson(route('games.finish', ['ramadan-race', $uuid]), ['position' => 1, 'field_size' => $field, 'race_time_ms' => (int) ($race->distance / 15 * 1000), 'top_speed_kmh' => 66])->assertOk();
        }

        $rows = $this->getJson(route('games.leaderboard', 'ramadan-race'))->json('rows');
        $this->assertSame(['Underdog · ••••0002', 'Favourite · ••••0001'], array_column($rows, 'player'));
        $this->assertGreaterThan($rows[1]['score'], $rows[0]['score']);
    }

    public function test_the_admin_adds_races_from_the_calendar_and_refreshes_them(): void
    {
        $campaign = $this->campaign();
        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        }, 'web');
        Auth::shouldUse('web');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $days = app(RealRaces::class)->raceDays();
        $this->assertNotEmpty($days);
        $this->assertNotEmpty(app(RealRaces::class)->racesOn('2026-04-18'));

        // the captured calendar's races are not in the captured meeting: the admin is told, nothing breaks
        $dayRace = array_key_first(app(RealRaces::class)->racesOn('2026-04-18'));
        Livewire::test(RacesRelationManager::class, ['ownerRecord' => $campaign, 'pageClass' => EditGameCampaign::class])
            ->callTableAction('add', data: ['day' => '2026-04-18', 'races' => [$dayRace]])
            ->assertHasNoTableActionErrors()
            ->assertNotified(__('games.errors.race_not_found'));
        $this->assertSame(0, GameRace::query()->count());

        $race = app(RealRaces::class)->import($campaign, 9178, '2021-11-19');
        Livewire::test(RacesRelationManager::class, ['ownerRecord' => $campaign, 'pageClass' => EditGameCampaign::class])
            ->assertCanSeeTableRecords([$race])
            ->callTableAction('refresh', $race)
            ->assertNotified(__('games.admin.field_refreshed'))
            ->mountTableAction('field', $race);

        // a race nobody rode can go; the view of its field names every horse
        $this->assertStringContainsString('Nawaya (OM)', view('filament.games.field', ['race' => $race])->render());
        Livewire::test(RacesRelationManager::class, ['ownerRecord' => $campaign, 'pageClass' => EditGameCampaign::class])->callTableAction('delete', $race);
        $this->assertSame(0, GameRace::query()->count());
    }

    /** @return list<array<string, mixed>> a hand-typed field, as the admin form sends it */
    private function typedRunners(int $count = 3): array
    {
        return array_map(fn (int $n) => [
            'no' => (string) $n, 'gate' => (string) $n, 'name' => ['en' => "Typed Horse {$n}", 'ar' => "حصان {$n}"],
            'rating' => (string) (50 + 10 * $n), 'weight' => '57', 'age' => '5', 'colour' => ['en' => 'Bay', 'ar' => ''],
            'jockey' => ['en' => "Jockey {$n}", 'ar' => ''], 'record' => ['starts' => '', 'first' => '', 'second' => '', 'third' => ''],
            'result' => $n === 2 ? '1' : null, 'silks_upload' => $n === 1 ? ['x' => 'games/silks/one.png'] : null,
        ], range(1, $count));
    }

    public function test_a_race_typed_by_hand_is_played_and_scored_like_a_club_race(): void
    {
        $campaign = $this->campaign();
        $race = app(RealRaces::class)->saveByHand($campaign, null, [
            'en_name' => 'Sponsor Cup', 'ar_name' => '', 'race_date' => '2027-03-05', 'post_time' => '16:30', 'distance' => '1400',
            'en_surface' => 'Dirt', 'ar_surface' => '', 'winning_time' => '1:32.40', 'runners' => $this->typedRunners(),
        ]);

        $this->assertTrue($race->isManual());
        $this->assertNull($race->source_race_id);
        $this->assertSame('Sponsor Cup', $race->ar_name); // an empty Arabic name falls back to the English one
        $this->assertSame(92_400, $race->winning_time_ms);
        $this->assertTrue($race->has_result);
        $first = $race->runner('1');
        $this->assertSame(['en' => 'Bay', 'ar' => 'Bay'], $first['colour']);
        $this->assertNull($first['record']); // empty boxes: no record rather than 0-0-0
        $this->assertSame(60, $first['rating']);
        $this->assertSame('games/silks/one.png', $first['silks_upload']);
        $this->assertSame(1, $race->ratingRank('3'));

        $player = $this->player();
        $runner = collect($this->actingAs($player, 'customer')->getJson(route('games.config', 'ramadan-race'))->json('campaign.races.0.runners'))->firstWhere('no', '1');
        $this->assertStringEndsWith('/storage/games/silks/one.png', $runner['silks_url']);

        $uuid = $this->actingAs($player, 'customer')->postJson(route('games.start', 'ramadan-race'), ['race' => $race->id, 'horse' => '1'])->assertOk()->json('attempt');
        $this->travel(150)->seconds();
        $this->actingAs($player, 'customer')->postJson(route('games.finish', ['ramadan-race', $uuid]), ['position' => 1, 'field_size' => 3, 'race_time_ms' => 94_000, 'top_speed_kmh' => 66])
            ->assertOk()->assertJsonPath('score', 150)->assertJsonPath('real.winner', 'Typed Horse 2')->assertJsonPath('real.winning_time', '1:32.40');
    }

    public function test_a_field_the_game_cannot_run_is_refused(): void
    {
        $campaign = $this->campaign();
        $base = ['en_name' => 'X', 'race_date' => '2027-03-05', 'distance' => 1200];

        foreach ([
            [$this->typedRunners(1), 'field_size'],
            [$this->typedRunners(17), 'field_size'],
            [array_map(fn ($r) => ['gate' => '1'] + $r, $this->typedRunners()), 'duplicate_gate'],
            [array_map(fn ($r) => ['no' => '1'] + $r, $this->typedRunners()), 'duplicate_no'],
        ] as [$runners, $reason]) {
            try {
                app(RealRaces::class)->saveByHand($campaign, null, $base + ['runners' => $runners]);
                $this->fail("expected {$reason}");
            } catch (GameException $e) {
                $this->assertSame($reason, $e->reason);
            }
        }
        $this->assertSame(0, GameRace::query()->count());
    }

    public function test_hand_edits_to_a_club_race_survive_a_refresh(): void
    {
        $race = $this->importedRace();
        $runners = collect($race->runners)->keyBy('no');
        $fromClub = $runners['3']['rating'];

        // the admin corrects horse 1's rating, takes horse 2 out and adds a horse the club does not list
        $edited = $runners->forget('2')->all();
        $edited['1']['rating'] = 40;
        $edited['99'] = RealRaces::cleanRunner($this->typedRunners()[0]) + [];
        $edited['99']['no'] = '99';
        $edited['99']['gate'] = 30;
        $edited = array_values($edited);
        $edited = array_values(array_filter($edited, fn ($r) => $r['no'] !== '17')); // keep 16
        $race = app(RealRaces::class)->saveByHand($race->campaign, $race, ['runners' => $edited]);

        $this->assertEqualsCanonicalizing(['1', '99'], $race->locked_nos);
        $this->assertEqualsCanonicalizing(['2', '17'], $race->removed_nos);
        $this->assertSame('National Museum 51st National Day Cup', $race->en_name); // a club race keeps its own details

        // the club's data for an untouched horse changes meanwhile: a refresh brings it back
        $runners = $race->runners;
        foreach ($runners as &$r) {
            if ($r['no'] === '3') {
                $r['rating'] = 1;
            }
        }
        $race->forceFill(['runners' => $runners])->save();

        $race = app(RealRaces::class)->import($race->campaign, 9178, '2021-11-19');

        $this->assertSame(40, $race->runner('1')['rating']); // the hand edit stays
        $this->assertSame(3, $race->runner('1')['result']); // and keeps its official place (3rd)
        $this->assertNull($race->runner('2'));
        $this->assertNull($race->runner('17'));
        $this->assertNotNull($race->runner('99'));
        $this->assertSame($fromClub, $race->runner('3')['rating']); // untouched horses follow the club
        $this->assertSame(15, $race->fieldSize()); // 16 from the club − 2 taken out + 1 added
    }

    public function test_the_admin_types_a_race_and_corrects_one_in_the_form(): void
    {
        $campaign = $this->campaign();
        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        }, 'web');
        Auth::shouldUse('web');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $manager = fn () => Livewire::test(RacesRelationManager::class, ['ownerRecord' => $campaign, 'pageClass' => EditGameCampaign::class]);

        $runners = array_map(fn ($r) => array_diff_key($r, ['silks_upload' => 1]), $this->typedRunners());
        $manager()->callTableAction('create', data: [
            'en_name' => 'Sponsor Cup', 'ar_name' => 'كأس الراعي', 'race_date' => '2027-03-05', 'post_time' => '16:30', 'distance' => 1400, 'runners' => $runners,
        ])->assertHasNoTableActionErrors();

        $race = GameRace::query()->sole();
        $this->assertSame('كأس الراعي', $race->ar_name);
        $this->assertSame(3, $race->fieldSize());

        // a mistyped time and a stall used twice are caught by the form (the server checks the field again)
        $manager()->callTableAction('edit', $race, data: ['winning_time' => 'soon'])->assertHasTableActionErrors(['winning_time']);
        $manager()->callTableAction('edit', $race, data: ['runners' => array_map(fn ($r) => ['gate' => '2'] + $r, $runners)])
            ->assertHasTableActionErrors();
        $this->assertSame([1, 2, 3], array_column($race->fresh()->runners, 'gate'));

        $manager()->callTableAction('edit', $race, data: ['distance' => 1600, 'winning_time' => '1:51.15'])->assertHasNoTableActionErrors();
        $this->assertSame(1600, $race->fresh()->distance);
        $this->assertSame(111_150, $race->fresh()->winning_time_ms);
        $manager()->assertTableActionHidden('refresh', $race); // nothing to refresh a typed race from
    }
}
