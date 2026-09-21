<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Tests\Concerns\FakesRacingSource;
use Tests\TestCase;

class RacingCalendarTest extends TestCase
{
    use FakesRacingSource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareRacingSite();
        $this->fakeRacingSource();
    }

    public function test_the_page_shows_the_seasons_and_the_whole_year(): void
    {
        $html = $this->get('/racing/calendar')
            ->assertOk()
            ->assertSee('Race Calendar')
            ->assertSee('<option value="26/27" selected>26/27</option>', false)
            ->assertSee('25 race days this season')
            ->getContent();

        // every season the source offers is in the dropdown, oldest last
        preg_match_all('#<option value="(\d\d/\d\d)"#', $html, $options);
        $this->assertCount(16, $options[1]);
        $this->assertSame(['26/27', '25/26'], array_slice($options[1], 0, 2));
        $this->assertSame('11/12', end($options[1]));

        // October 2026 to September 2027, twelve months
        preg_match_all('#<caption[^>]*>\s*([^<]+?)\s*</caption>#', $html, $m);
        $this->assertSame(['October 2026', 'November 2026', 'December 2026', 'January 2027', 'February 2027', 'March 2027', 'April 2027', 'May 2027', 'June 2027', 'July 2027', 'August 2027', 'September 2027'], $m[1]);

        // race days are links (with the meeting name), the other days are not
        $this->assertSame(25, substr_count($html, '/racing/calendar/20'));
        $this->assertStringContainsString('href="' . url('/racing/calendar/2026-10-17') . '"', $html);
        $this->assertStringContainsString('title="1st Meeting"', $html);
        $this->assertStringContainsString('title="Final Meeting"', $html);
    }

    public function test_weeks_start_on_saturday_and_dates_land_in_the_right_column(): void
    {
        $html = $this->get('/racing/calendar')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<th[^>]*>\s*Sat\s*</th>\s*<th[^>]*>\s*Sun\s*</th>#', $html);

        // 17 October 2026 is a Saturday: the first column of its week
        $this->assertMatchesRegularExpression('#<tr>\s*<td[^>]*>\s*<a href="[^"]*/racing/calendar/2026-10-17"#', $html);
    }

    public function test_choosing_a_season_asks_the_source_for_exactly_that_seasons_dates(): void
    {
        $this->get('/racing/calendar?season=25/26')
            ->assertOk()
            ->assertSee('<option value="25/26" selected>25/26</option>', false)
            ->assertSee('26 race days this season')
            ->assertSee('/racing/calendar/2025-10-18', false);

        $this->assertSame(1, $this->sentCount('dateFrom=2025-10-01&dateTo=2026-09-30'));
    }

    public function test_an_unknown_season_falls_back_and_never_sends_junk_dates_to_the_source(): void
    {
        foreach (['99/99', '../../x', "25/26'", 'abc', ''] as $season) {
            $this->get('/racing/calendar?season=' . urlencode($season))->assertOk()->assertSee('<option value="26/27" selected>26/27</option>', false);
        }

        $this->get('/racing/calendar?season[]=25/26')->assertOk();

        // the source answers a malformed range with every race it has ever run, so only the default range may be requested
        $this->assertSame(1, $this->sentCount('method=getRaceDetailsWeb'));
        $this->assertSame(1, $this->sentCount('dateFrom=2026-10-01&dateTo=2027-09-30'));
    }

    public function test_a_season_with_no_meetings_says_so(): void
    {
        // the source's season list includes 24/25, but our fake answers it with `[]`
        $this->get('/racing/calendar?season=24/25')
            ->assertOk()
            ->assertSee('No race days are scheduled for this season.')
            ->assertSee('September 2025');
    }

    public function test_seasons_and_days_are_cached(): void
    {
        $this->get('/racing/calendar')->assertOk();
        $this->get('/racing/calendar')->assertOk();
        $this->get('/racing/calendar?season=26/27')->assertOk();

        $this->assertSame(1, $this->sentCount('page=RaceCalendar'));
        $this->assertSame(1, $this->sentCount('method=getRaceDetailsWeb'));
    }

    public function test_the_season_form_submits_back_to_the_page_it_is_on(): void
    {
        $this->get('/racing/calendar')->assertSee('action="' . url('/racing/calendar') . '#race-calendar"', false);
    }

    public function test_arabic_month_names_and_labels(): void
    {
        $this->get('/racing/calendar?lang=ar')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('تقويم السباقات')
            ->assertSee('أكتوبر 2026')
            ->assertSee('الموسم')
            ->assertSee('1st Meeting'); // the source only names meetings in English
    }

    public function test_the_calendar_is_part_of_the_racing_nav(): void
    {
        $this->get('/racing/results/9178')->assertOk()->assertSee('/racing/calendar', false);
    }

    public function test_an_outage_is_a_friendly_message(): void
    {
        $this->replaceRacingFake(fn () => throw new ConnectionException('timeout'));

        $this->get('/racing/calendar')->assertOk()->assertSee('temporarily unavailable')->assertDontSee('<select', false);
    }

    public function test_a_race_day_before_the_meeting_has_run_goes_to_its_entries(): void
    {
        $this->get('/racing/calendar/2026-10-17')->assertRedirect('/racing/entries/10005');
    }

    public function test_a_race_day_that_has_run_goes_to_its_results(): void
    {
        $this->get('/racing/calendar/2026-04-18')->assertRedirect('/racing/results/9822');
    }

    public function test_dates_that_are_not_race_days_are_404_without_asking_the_source_for_a_link(): void
    {
        foreach (['2026-10-18', '2001-01-01', '2026-10-17x', '2026-10', '20261017'] as $date) {
            $this->get("/racing/calendar/{$date}")->assertNotFound();
        }

        $this->assertSame(0, $this->sentCount('method=getRaceLinkByDate'));
    }

    public function test_impossible_dates_are_404_without_calling_the_source_at_all(): void
    {
        Http::fake();

        foreach (['2026-13-45', '2026-02-30', '2026-00-10', '1999-10-17', '2026-10-17%27'] as $date) {
            $this->get("/racing/calendar/{$date}")->assertNotFound();
        }

        Http::assertNothingSent();
    }

    public function test_an_outage_while_following_a_date_keeps_you_on_the_calendar(): void
    {
        $this->get('/racing/calendar')->assertOk(); // warm the season and its days

        $this->replaceRacingFake(fn () => throw new ConnectionException('timeout'));
        // the days are gone with the cache, so the lookup itself fails
        $this->get('/racing/calendar/2026-10-17')->assertOk()->assertSee('temporarily unavailable');
    }

    public function test_the_cms_block_renders_the_calendar_with_its_own_heading(): void
    {
        $html = View::make('cms.blocks.race_calendar', [
            'data' => ['heading' => ['en' => 'Our Race Days', 'ar' => 'أيام السباق']],
        ])->render();

        $this->assertStringContainsString('Our Race Days', $html);
        $this->assertStringContainsString('id="race-calendar"', $html);
        $this->assertStringContainsString('/racing/calendar/2026-10-17', $html);
    }

    public function test_the_cms_block_defaults_its_heading_and_survives_an_outage(): void
    {
        $this->replaceRacingFake(fn () => throw new ConnectionException('timeout'));

        $html = View::make('cms.blocks.race_calendar', ['data' => []])->render();

        $this->assertStringContainsString('Race Calendar', $html);
        $this->assertStringContainsString('temporarily unavailable', $html);
    }

    public function test_the_block_is_registered_in_the_page_builder(): void
    {
        $names = array_map(fn ($block) => $block->getName(), \App\Filament\Blocks\Cms\CmsBlocks::all());

        $this->assertContains('race_calendar', $names);
    }
}
