<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesRacingSource;
use Tests\TestCase;

class RacingHandicapTest extends TestCase
{
    use FakesRacingSource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareRacingSite();
        $this->fakeRacingSource();
    }

    /** @return list<array<string, mixed>> the horses on the requested page, in display order */
    private function horses(string $url): array
    {
        return $this->get($url)->assertOk()->viewData('horses')->items();
    }

    public function test_lists_horses_with_links_ratings_and_changes(): void
    {
        $this->get('/racing/handicap-ratings')
            ->assertOk()
            ->assertSee('Handicap Ratings')
            ->assertSee('Al Qous (OM)')
            ->assertSee('/racing/horse/1205', false)
            ->assertSee('70')
            ->assertSee('NOR')
            ->assertSee('21 Nov 2021')
            ->assertSee('rating went down')
            ->assertSee('rating went up');
    }

    public function test_defaults_to_all_breeds_and_locations_sorted_by_name(): void
    {
        $names = array_column($this->horses('/racing/handicap-ratings'), 'name');

        $this->assertCount(31, $names);
        $sorted = $names;
        usort($sorted, fn ($a, $b) => strcmp(mb_strtolower($a), mb_strtolower($b)));
        $this->assertSame($sorted, $names);

        $this->assertSame(1, $this->sentCount('horseBreed=&horseLoc=1'));
    }

    public function test_breed_and_location_are_passed_to_the_source(): void
    {
        $this->get('/racing/handicap-ratings?breed=pa&location=local')->assertOk();
        $this->get('/racing/handicap-ratings?breed=tb&location=non-local')->assertOk();

        $this->assertSame(1, $this->sentCount('horseBreed=PA&horseLoc=2'));
        $this->assertSame(1, $this->sentCount('horseBreed=TB&horseLoc=3'));
    }

    public function test_name_search_filters_locally(): void
    {
        $names = array_column($this->horses('/racing/handicap-ratings?q=QOUS'), 'name');

        $this->assertSame(['Al Qous (OM)'], $names);
        $this->assertSame(1, $this->sentCount('method=getHandicapRatings'), 'searching does not ask the source again');
    }

    public function test_no_matches_message(): void
    {
        $this->get('/racing/handicap-ratings?q=zzzzzz')->assertOk()->assertSee('No results found');
    }

    public function test_sorting_by_rating_puts_unrated_horses_last_in_either_direction(): void
    {
        foreach (['desc', 'asc'] as $dir) {
            $horses = $this->horses("/racing/handicap-ratings?sort=rating&dir={$dir}");
            $rated = array_values(array_filter(array_column($horses, 'rating'), fn ($r) => $r !== null));

            $expected = $rated;
            $dir === 'desc' ? rsort($expected) : sort($expected);

            $this->assertSame($expected, $rated, $dir);
            $this->assertNull(end($horses)['rating'], "unrated last ({$dir})");
            $this->assertNotNull($horses[0]['rating'], "rated first ({$dir})");
        }
    }

    public function test_sorting_by_last_ran_newest_first(): void
    {
        $dates = array_column($this->horses('/racing/handicap-ratings?sort=last_ran&dir=desc'), 'last_ran');
        $dates = array_values(array_filter($dates));

        $sorted = $dates;
        rsort($sorted);
        $this->assertSame($sorted, $dates);
    }

    public function test_paginates_and_keeps_the_filters_in_page_links(): void
    {
        config(['racing.handicap_per_page' => 10]);

        $page2 = $this->get('/racing/handicap-ratings?sort=rating&dir=desc&page=2')->assertOk();

        $this->assertCount(10, $page2->viewData('horses')->items());
        $page2->assertSee('Showing 11–20 of 31')
            ->assertSee('sort=rating', false)
            ->assertSee('dir=desc', false);

        $this->assertCount(1, $this->horses('/racing/handicap-ratings?page=4'));
        $this->assertSame([], $this->horses('/racing/handicap-ratings?page=99'));
    }

    public function test_sort_links_toggle_direction(): void
    {
        $this->get('/racing/handicap-ratings?sort=rating&dir=asc')
            ->assertOk()
            ->assertSee('aria-sort="ascending"', false)
            ->assertSee('sort=rating&amp;dir=desc', false);
    }

    public function test_hostile_query_values_fall_back_to_defaults(): void
    {
        $this->get('/racing/handicap-ratings?breed=zz&location[]=x&sort=DROP&dir=up&q[]=a&page=-5')
            ->assertOk()
            ->assertSee('Al Qous (OM)');

        // only whitelisted values ever reach the source
        $this->assertSame(1, $this->sentCount('horseBreed=&horseLoc=1'));
    }

    public function test_the_full_list_is_cached(): void
    {
        $this->get('/racing/handicap-ratings')->assertOk();
        $this->get('/racing/handicap-ratings?sort=rating')->assertOk();
        $this->get('/racing/handicap-ratings?q=al')->assertOk();

        $this->assertSame(1, $this->sentCount('method=getHandicapRatings'));
    }

    public function test_an_error_page_from_the_source_is_a_friendly_message(): void
    {
        // the grid answers with a <script> "showError()" page (HTTP 200) when something goes wrong
        $this->replaceRacingFake(['*' => Http::response("<script>pop_win({title: 'Error'});</script>")]);

        $this->get('/racing/handicap-ratings')->assertOk()->assertSee('temporarily unavailable');
    }

    public function test_arabic(): void
    {
        $this->get('/racing/handicap-ratings?lang=ar')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('السلالة');

        $this->assertGreaterThan(0, $this->sentCount('lang=ar'));
    }

    public function test_the_warm_command_fills_the_cache_for_every_language(): void
    {
        $this->artisan('racing:warm')
            ->expectsOutputToContain('Handicap ratings (en): 31 horses cached.')
            ->expectsOutputToContain('Handicap ratings (ar): 31 horses cached.')
            ->assertSuccessful();

        $this->get('/racing/handicap-ratings')->assertOk();
        $this->get('/racing/handicap-ratings?lang=ar')->assertOk();

        // 2 from the command, none from the visitors
        $this->assertSame(2, $this->sentCount('method=getHandicapRatings'));
    }

    public function test_the_warm_command_reports_failure_when_the_source_is_down(): void
    {
        $this->replaceRacingFake(['*' => Http::response('boom', 500)]);

        $this->artisan('racing:warm')->assertFailed();
    }
}
