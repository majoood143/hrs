<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesRacingSource;
use Tests\TestCase;

/** /api/v1/racing: the website's racing pages for the app, from the same (faked) source. */
class RacingTest extends TestCase
{
    use FakesRacingSource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareRacingSite();

        // the search page, in front of the shared fake (a stub that answers null hands over to the next)
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);

            if (($q['page'] ?? null) !== 'Search') {
                return null;
            }

            return Http::response($this->fixture(match (true) {
                ($q['QRECsearch'] ?? '') === 'zzzzqq' => 'search_empty.html',
                (int) $q['type'] === 0 => 'search_all.html',
                (int) $q['type'] === 2 => 'search_owner.html',
                default => 'search_horse.html',
            }));
        });
        $this->fakeRacingSource();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ── Search ────────────────────────────────────────────────────────────────

    public function test_search_all_gives_a_preview_of_each_group_with_its_total(): void
    {
        $response = $this->getJson('/api/v1/racing/search?q=desert')->assertOk()->assertJsonPath('meta.query', 'desert');

        $groups = collect($response->json('data'));
        $this->assertNotEmpty($groups);
        $this->assertSame($groups->pluck('group')->all(), array_values(array_intersect(['horse', 'owner', 'jockey', 'trainer'], $groups->pluck('group')->all())));

        foreach ($groups as $group) {
            $this->assertLessThanOrEqual(config('racing.all_preview'), count($group['rows']));
            $this->assertGreaterThanOrEqual(count($group['rows']), $group['total']);
            $this->assertIsInt($group['rows'][0]['id']);
            $this->assertCount(count($group['headers']), $group['rows'][0]['cells']);
        }
    }

    public function test_one_type_is_paginated(): void
    {
        $this->getJson('/api/v1/racing/search?q=desert&type=horse')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.group', 'horse')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', config('racing.per_page'));

        $this->getJson('/api/v1/racing/search?q=zzzzqq&type=owner')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_a_short_query_or_an_unknown_type_is_refused_before_asking_the_source(): void
    {
        $this->getJson('/api/v1/racing/search?q=ab')->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson('/api/v1/racing/search?q=desert&type=5')->assertUnprocessable()->assertJsonValidationErrors('type');

        $this->assertSame(0, $this->sentCount('page=Search'));
    }

    // ── Profiles ──────────────────────────────────────────────────────────────

    public function test_a_horse_profile_in_both_languages_with_links_and_proxied_images(): void
    {
        $en = $this->getJson('/api/v1/racing/horse/1205')
            ->assertOk()
            ->assertJsonPath('data.entity', 'horse')
            ->assertJsonPath('data.id', 1205)
            ->assertJsonPath('data.web_url', route('racing.profile', ['horse', 1205]))
            ->json('data');

        $this->assertNotSame('', $en['title']);
        $this->assertNotEmpty($en['tabs']);

        if ($en['image_url'] !== null) {
            $this->assertStringStartsWith(route('racing.image'), $en['image_url']);
        }

        $links = collect($en['facts'])->pluck('link')
            ->merge(collect($en['tabs'])->flatMap(fn ($tab) => collect($tab['tables'])->flatMap(fn ($table) => collect($table['rows'])->flatten(1)->pluck('link'))))
            ->filter();
        $this->assertNotEmpty($links);
        $links->each(fn ($link) => $this->assertContains($link['type'], ['race', 'horse', 'owner', 'jockey', 'trainer']));

        $ar = $this->getJson('/api/v1/racing/horse/1205', ['Accept-Language' => 'ar'])->assertOk()->json('data');
        $this->assertNotSame($en['tabs'][0]['label'], $ar['tabs'][0]['label']);
    }

    public function test_an_unknown_profile_is_a_404(): void
    {
        $this->getJson('/api/v1/racing/horse/4242')->assertNotFound();
        $this->getJson('/api/v1/racing/rider/1205')->assertNotFound();
    }

    // ── Calendar and race days ────────────────────────────────────────────────

    public function test_the_calendar_lists_a_seasons_race_days_and_the_day_to_open_on(): void
    {
        Carbon::setTestNow('2026-09-20 12:00:00');

        $data = $this->getJson('/api/v1/racing/calendar?season=26/27')->assertOk()->json('data');

        $this->assertSame('26/27', $data['season']);
        $this->assertContains('26/27', $data['seasons']);
        $this->assertSame(['date' => '2026-10-17', 'meeting' => '1st Meeting'], $data['days'][0]);
        $this->assertSame('2026-10-17', $data['next_day']);

        // an unknown season falls back to the newest, never reaching the source as typed
        $this->getJson('/api/v1/racing/calendar?season=1999-2000')->assertOk()->assertJsonPath('data.season', $data['seasons'][0]);
    }

    public function test_a_race_day_lists_its_races_and_their_pages(): void
    {
        $data = $this->getJson('/api/v1/racing/days/2026-10-17')->assertOk()->json('data');

        $this->assertSame('2026-10-17', $data['date']);
        $this->assertNotEmpty($data['races']);
        $this->assertSame(['entries', 'card', 'results'], array_keys($data['races'][0]['pages']));
        $this->assertIsInt($data['races'][0]['id']);
    }

    public function test_a_date_that_is_not_a_race_day_is_a_404_without_asking_for_its_races(): void
    {
        $this->getJson('/api/v1/racing/days/2026-10-18')->assertNotFound();
        $this->getJson('/api/v1/racing/days/2026-02-31')->assertNotFound();

        $this->assertSame(0, $this->sentCount('dateTo=2026-10-18'));
    }

    // ── Race day pages ────────────────────────────────────────────────────────

    public function test_results_give_the_meeting_its_races_and_the_selected_races_table(): void
    {
        $data = $this->getJson('/api/v1/racing/races/results/9178')
            ->assertOk()
            ->assertJsonPath('data.page', 'results')
            ->assertJsonPath('data.race_id', 9178)
            ->assertJsonPath('data.detail.available', true)
            ->json('data');

        $this->assertContains(9178, array_column($data['races'], 'id'));
        $this->assertNotEmpty($data['detail']['table']['rows']);
        $this->assertSame(route('racing.meeting', ['page' => 'results', 'race' => 9178]), $data['web_url']);
    }

    public function test_the_race_card_and_form_guide_give_runners(): void
    {
        $card = $this->getJson('/api/v1/racing/races/card/9178')->assertOk()->assertJsonPath('data.detail.available', true)->json('data.detail.runners.0');
        $this->assertArrayHasKey('weight', $card);
        $this->assertIsInt($card['horse']['id']);

        $guide = $this->getJson('/api/v1/racing/races/form-guide/9178')->assertOk()->json('data.detail.runners.0');
        $this->assertArrayHasKey('runs', $guide);
        $this->assertArrayNotHasKey('weight', $guide);
    }

    public function test_a_race_with_nothing_yet_says_so(): void
    {
        // the next meeting (the source's default for entries): declared races, nothing entered yet
        $this->getJson('/api/v1/racing/races/entries')
            ->assertOk()
            ->assertJsonPath('data.race_id', 10005)
            ->assertJsonPath('data.detail', ['available' => false]);
    }

    public function test_an_unknown_race_is_a_404_and_never_reaches_the_slow_meeting_pages(): void
    {
        $this->getJson('/api/v1/racing/races/card/99999999')->assertNotFound();
        $this->getJson('/api/v1/racing/races/podium/9178')->assertNotFound();

        $this->assertSame(0, $this->sentCount('page=RaceCard'));
    }

    public function test_without_a_race_the_latest_meeting_opens(): void
    {
        $this->getJson('/api/v1/racing/races/results')->assertOk()->assertJsonPath('data.page', 'results')->assertJsonStructure(['data' => ['races', 'race_id', 'detail']]);
    }

    // ── Handicap ratings ──────────────────────────────────────────────────────

    public function test_handicap_ratings_are_filtered_sorted_and_paginated_like_the_website(): void
    {
        $all = $this->getJson('/api/v1/racing/handicap?sort=rating&dir=desc')->assertOk()->assertJsonStructure(['data', 'links', 'meta'])->json();

        $ratings = array_values(array_filter(array_column($all['data'], 'rating'), fn ($r) => $r !== null));
        $sorted = $ratings;
        rsort($sorted);
        $this->assertSame($sorted, $ratings);

        $name = $all['data'][0]['name'];
        $this->getJson('/api/v1/racing/handicap?q='.urlencode(mb_substr($name, 0, 4)))->assertOk()->assertJsonPath('data.0.name', fn ($n) => mb_stripos($n, mb_substr($name, 0, 4)) !== false);

        $this->getJson('/api/v1/racing/handicap?breed=xx')->assertUnprocessable()->assertJsonValidationErrors('breed');
    }

    // ── The source is down ────────────────────────────────────────────────────

    public function test_a_source_outage_is_a_503_the_app_can_retry(): void
    {
        $this->replaceRacingFake(fn () => Http::response('down', 500));

        $this->getJson('/api/v1/racing/horse/1205')->assertStatus(503)->assertJsonPath('code', 'racing_unavailable');
        $this->getJson('/api/v1/racing/calendar')->assertStatus(503)->assertJsonPath('code', 'racing_unavailable');
    }
}
