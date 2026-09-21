<?php

namespace Tests\Feature;

use App\Filament\Blocks\Cms\CmsBlocks;
use App\Services\Racing\RacingCalendarParser;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Tests\Concerns\FakesRacingSource;
use Tests\TestCase;

class RacingWidgetTest extends TestCase
{
    use FakesRacingSource;

    protected function setUp(): void
    {
        parent::setUp();

        // between seasons: 25/26 is over and 26/27's first meeting (17 Oct 2026) is the next race day
        Carbon::setTestNow('2026-09-20 12:00:00');

        $this->prepareRacingSite();
        $this->fakeRacingSource();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Render the block as it would be on a page at /home, with the given query string. */
    private function widget(string $query = '', array $data = [], string $locale = 'en'): string
    {
        app()->setLocale($locale);
        $this->app->instance('request', Request::create('/home' . $query));

        return View::make('cms.blocks.race_widget', ['data' => $data])->render();
    }

    private function racesRequests(string $date): int
    {
        return $this->sentCount("dateFrom={$date}&dateTo={$date}");
    }

    public function test_it_opens_on_the_next_race_day_like_the_source_widget(): void
    {
        $html = $this->widget();

        $this->assertStringContainsString('id="race-widget"', $html);
        $this->assertStringContainsString('Saturday 17 October 2026', $html);
        $this->assertStringContainsString('October 2026', $html);
        $this->assertMatchesRegularExpression('#Races:</dt><dd[^>]*>8</dd>#', $html);
        $this->assertStringContainsString('1st Meeting', $html);
        $this->assertStringContainsString('13:30', $html);
        $this->assertStringContainsString('3YO Maiden Plate (Local Bred)', $html);
        $this->assertStringContainsString('1,600 m', $html);
        $this->assertStringContainsString('3YO&amp;+ R48-NOR', $html);
        $this->assertSame(8, substr_count($html, '/racing/race/'));

        // nothing is published for an upcoming meeting: no links, and it says why
        $this->assertStringNotContainsString('/racing/results/', $html);
        $this->assertStringNotContainsString('/racing/entries/', $html);
        $this->assertStringContainsString('appear here once they are published', $html);

        // the selected day is marked, and the other October race days are links to their own day
        $this->assertMatchesRegularExpression('#href="[^"]*race_month=2026-10&amp;race_date=2026-10-17[^"]*"[^>]*aria-current="date"#', $html);
        $this->assertStringContainsString('race_date=2026-10-24', $html);
        $this->assertStringContainsString('race_date=2026-10-30', $html);

        // one call for the season's days (plus the seasons page) and one for the day's races, with a page size
        $this->assertSame(1, $this->racesRequests('2026-10-17'));
        $this->assertSame(1, $this->sentCount('rows=100'));
    }

    public function test_a_past_day_links_each_race_to_what_exists(): void
    {
        $html = $this->widget('?race_month=2026-04&race_date=2026-04-18');

        $this->assertStringContainsString('Saturday 18 April 2026', $html);
        $this->assertStringContainsString('April 2026', $html);
        $this->assertStringContainsString('Final Meeting', $html);
        $this->assertMatchesRegularExpression('#Races:</dt><dd[^>]*>5</dd>#', $html);

        foreach (['results', 'entries', 'card'] as $page) {
            $this->assertStringContainsString('href="' . url("/racing/{$page}/9822") . '"', $html);
        }

        // video links go straight out to the source's YouTube URL, in a new tab and without leaking the opener
        $this->assertMatchesRegularExpression('#<a href="https://youtu\.be/[^"]+"\s+target="_blank" rel="noopener noreferrer"#', $html);
        $this->assertStringNotContainsString('appear here once they are published', $html);
    }

    public function test_a_relative_photo_link_is_resolved_against_the_source(): void
    {
        $body = json_encode(['ROWS' => [[1, 'R', 1200.0, '', "<a class='cphoto' href='/img/photofinish/1.jpg'>x</a>", '', '', '', 'M', '', '']]]);

        $races = $this->app->make(RacingCalendarParser::class)->parseRaces($body);

        $this->assertSame('http://racing.test/img/photofinish/1.jpg', $races[0]['photo']);
        $this->assertNull($races[0]['time']);
    }

    public function test_a_race_without_results_only_offers_what_the_source_has(): void
    {
        $html = $this->widget('?race_month=2026-04&race_date=2026-04-02');

        $this->assertMatchesRegularExpression('#Races:</dt><dd[^>]*>9</dd>#', $html);
        $this->assertSame(9, substr_count($html, '/racing/entries/'));
        $this->assertSame(8, substr_count($html, '/racing/results/')); // race 9968 has not been resulted
        $this->assertStringNotContainsString('/racing/results/9968', $html);
        $this->assertStringContainsString('/racing/entries/9968', $html);
    }

    public function test_month_arrows_keep_the_selected_day_and_stop_at_the_ends_of_the_data(): void
    {
        $html = $this->widget('?race_date=2026-10-17');

        $this->assertStringContainsString('aria-label="Previous month"', $html);
        $this->assertStringContainsString('aria-label="Next month"', $html);
        $this->assertStringContainsString('race_month=2026-09&amp;race_date=2026-10-17', $html);
        $this->assertStringContainsString('race_month=2026-11&amp;race_date=2026-10-17', $html);

        // browsing another month leaves the chosen day's races on screen
        $november = $this->widget('?race_month=2026-11&race_date=2026-10-17');
        $this->assertStringContainsString('November 2026', $november);
        $this->assertStringContainsString('Saturday 17 October 2026', $november);

        // September 2027 is the last month of the newest season, October 2011 the first of the oldest
        $this->assertStringNotContainsString('aria-label="Next month"', $this->widget('?race_month=2027-09'));
        $this->assertStringNotContainsString('aria-label="Previous month"', $this->widget('?race_month=2011-10'));
    }

    public function test_the_weeks_start_on_saturday(): void
    {
        $html = $this->widget();

        $this->assertMatchesRegularExpression('#<th[^>]*>\s*Sat\s*</th>\s*<th[^>]*>\s*Sun\s*</th>#', $html);
        // 17 October 2026 is a Saturday: first column
        $this->assertMatchesRegularExpression('#<tr>\s*<td[^>]*>\s*<a href="[^"]*race_date=2026-10-17#', $html);
    }

    public function test_junk_and_non_race_days_never_reach_the_source(): void
    {
        foreach (['2026-10-18', '2026-02-30', '99999999', '<script>', '', '2026-10-17%27', '../etc'] as $date) {
            $html = $this->widget('?race_date=' . urlencode($date));

            $this->assertStringContainsString('Saturday 17 October 2026', $html, "falls back to the default for {$date}");
        }

        $this->widget('?race_date[]=2026-10-17&race_month[]=2026-10');
        $this->widget('?race_month=1999-01&race_date=2026-10-17');
        $this->widget('?race_month=2026-13');

        // every date range the source was asked for is a season we built or the one real race day
        $requests = Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), 'method=getRaceDetailsWeb'));

        foreach ($requests as [$request]) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);

            $this->assertContains($q['dateFrom'], ['2025-10-01', '2026-10-01', '2026-10-17'], 'no unvetted date range was requested');
        }
    }

    public function test_it_falls_back_to_the_last_race_day_when_no_later_season_exists(): void
    {
        Carbon::setTestNow('2027-11-20 12:00:00'); // 27/28 is not offered by the source

        $html = $this->widget();

        // the newest season (26/27) has meetings, and its last one is the latest to show
        $this->assertMatchesRegularExpression('#(Friday|Saturday|Sunday|Monday|Tuesday|Wednesday|Thursday) \d{1,2} [A-Z][a-z]+ 2027#', $html);
    }

    public function test_a_day_with_no_races_says_so(): void
    {
        $this->replaceRacingFake(function (HttpRequest $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);

            return match (true) {
                ($q['page'] ?? null) === 'RaceCalendar' => Http::response($this->fixture('calendar_page.html')),
                ($q['dateFrom'] ?? null) === ($q['dateTo'] ?? 0) => Http::response('{"TOTAL":0,"RECORDS":0,"PAGE":0,"ROWS":""}'),
                ($q['dateFrom'] ?? null) === '2026-10-01' => Http::response($this->fixture('calendar_days_2627.json')),
                default => Http::response('[]'),
            };
        });

        $html = $this->widget();

        $this->assertStringContainsString('Saturday 17 October 2026', $html);
        $this->assertStringContainsString('No races are listed for this day.', $html);
        $this->assertMatchesRegularExpression('#Races:</dt><dd[^>]*>0</dd>#', $html);
    }

    public function test_a_failing_day_lookup_keeps_the_calendar_usable(): void
    {
        $this->replaceRacingFake(function (HttpRequest $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);

            return match (true) {
                ($q['page'] ?? null) === 'RaceCalendar' => Http::response($this->fixture('calendar_page.html')),
                ($q['dateFrom'] ?? null) === ($q['dateTo'] ?? 0) => Http::response('<script>showError()</script>'),
                ($q['dateFrom'] ?? null) === '2026-10-01' => Http::response($this->fixture('calendar_days_2627.json')),
                default => Http::response('[]'),
            };
        });

        $html = $this->widget();

        $this->assertStringContainsString('temporarily unavailable', $html);
        $this->assertStringContainsString('race_date=2026-10-24', $html); // the month is still navigable
    }

    public function test_an_outage_is_a_friendly_message(): void
    {
        $this->replaceRacingFake(fn () => throw new ConnectionException('timeout'));

        $html = $this->widget();

        $this->assertStringContainsString('temporarily unavailable', $html);
        $this->assertStringNotContainsString('Previous month', $html);
    }

    public function test_arabic_labels_dates_and_mirrored_arrows(): void
    {
        $html = $this->widget(locale: 'ar');

        $this->assertStringContainsString('أكتوبر 2026', $html);
        $this->assertStringContainsString('عدد الأشواط:', $html);
        $this->assertStringContainsString('وقت الانطلاق:', $html);
        $this->assertStringContainsString('الشهر التالي', $html);
        $this->assertStringContainsString('1,600 م', $html);
        $this->assertStringContainsString('rtl:rotate-180', $html);
    }

    public function test_the_races_are_cached_per_language(): void
    {
        $this->widget();
        $this->widget();
        $this->assertSame(1, $this->racesRequests('2026-10-17'));

        $this->widget(locale: 'ar');
        $this->assertSame(2, $this->racesRequests('2026-10-17'));
    }

    public function test_each_race_button_has_an_icon_and_results_is_the_primary_one(): void
    {
        $html = $this->widget('?race_month=2026-04&race_date=2026-04-18');

        // one shared sprite, referenced by <use>
        foreach (['trophy', 'card', 'entries', 'play', 'camera', 'prev', 'next'] as $icon) {
            $this->assertStringContainsString('<symbol id="rw-' . $icon . '"', $html);
        }

        $this->assertSame(1, substr_count($html, '<symbol id="rw-trophy"'));
        $this->assertSame(5, substr_count($html, '<use href="#rw-trophy"/>'));
        $this->assertSame(5, substr_count($html, '<use href="#rw-play"/>'));
        $this->assertSame(0, substr_count($html, '<use href="#rw-camera"/>')); // no photo on this day

        // results comes first and is the filled button
        $this->assertMatchesRegularExpression('#<a href="[^"]*/racing/results/9822"[^>]*bg-warm-600 text-white[^>]*>\s*<svg[^>]*><use href="\#rw-trophy"/></svg>\s*Results#', $html);
        $this->assertLessThan(strpos($html, '/racing/card/9822'), strpos($html, '/racing/results/9822'));
        $this->assertLessThan(strpos($html, '/racing/entries/9822'), strpos($html, '/racing/card/9822'));
    }

    public function test_the_controls_are_marked_for_the_script_and_still_plain_links(): void
    {
        $html = $this->widget('?race_date=2026-10-17');

        $this->assertStringContainsString('data-race-widget data-endpoint="' . url('/racing/widget') . '"', $html);
        $this->assertStringContainsString('data-rw="prev"', $html);
        $this->assertStringContainsString('data-rw="next"', $html);
        $this->assertStringContainsString('data-rw="day"', $html);
        $this->assertStringContainsString('data-rw-panel', $html);
        $this->assertStringContainsString('data-rw-focus', $html);
    }

    public function test_the_fragment_route_returns_the_widget_with_links_back_to_the_host_page(): void
    {
        $response = $this->get('/racing/widget?from=/home&race_month=2026-04&race_date=2026-04-02&season=25/26')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex')
            ->assertSee('Thursday 2 April 2026')
            ->assertSee('id="race-widget"', false);

        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=30', $response->headers->get('Cache-Control'));

        $html = $response->getContent();

        // it is not a page: no layout, and no link points at the fragment route itself
        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringNotContainsString('href="' . url('/racing/widget'), $html);

        // month / day links go back to /home, keeping the host page's own query string, without `from`
        $this->assertMatchesRegularExpression('#href="' . preg_quote(url('/home'), '#') . '\?season=25%2F26&amp;race_month=2026-03&amp;race_date=2026-04-02\#race-widget"#', $html);
        $this->assertStringNotContainsString('from=', $html);
    }

    public function test_the_fragment_route_only_ever_links_to_a_path_on_this_site(): void
    {
        foreach (['//evil.test/x', 'https://evil.test', 'javascript:alert(1)', '/a b', '\\evil.test', '', 'home'] as $from) {
            $html = $this->get('/racing/widget?race_date=2026-10-17&from=' . urlencode($from))->assertOk()->getContent();

            $this->assertStringNotContainsString('evil.test', $html, "from={$from}");
            $this->assertStringNotContainsString('javascript:', $html, "from={$from}");
            $this->assertStringContainsString('href="' . url('/') . '?race_month=2026-11&amp;race_date=2026-10-17#race-widget"', $html, "from={$from}");
        }
    }

    public function test_the_fragment_route_follows_the_visitors_language(): void
    {
        $this->get('/racing/widget?from=/home&lang=ar')
            ->assertOk()
            ->assertSee('عدد الأشواط:')
            ->assertSee('أكتوبر 2026');

        // the choice sticks in the session, which is how the script's follow-up requests stay in Arabic
        $this->get('/racing/widget?from=/home')->assertOk()->assertSee('عدد الأشواط:');
    }

    public function test_a_finished_race_day_is_cached_far_longer_than_an_upcoming_one(): void
    {
        $this->widget('?race_date=2026-04-18');
        $this->widget();
        $this->assertSame(1, $this->racesRequests('2026-04-18'));
        $this->assertSame(1, $this->racesRequests('2026-10-17'));

        Carbon::setTestNow(now()->addHours(2));

        $this->widget('?race_date=2026-04-18');
        $this->widget();

        $this->assertSame(1, $this->racesRequests('2026-04-18'), 'the archive day is still cached');
        $this->assertSame(2, $this->racesRequests('2026-10-17'), 'the upcoming day was refreshed');
    }

    public function test_the_block_uses_its_own_heading_or_a_default(): void
    {
        $custom = $this->widget(data: ['heading' => ['en' => 'This Week at the Track', 'ar' => 'هذا الأسبوع']]);
        $this->assertStringContainsString('This Week at the Track', $custom);

        $default = $this->widget();
        $this->assertStringContainsString('<h2 class="font-display text-2xl font-semibold text-warm-900 sm:text-3xl">Races</h2>', $default);
    }

    public function test_the_block_is_registered_in_the_page_builder(): void
    {
        $names = array_map(fn ($block) => $block->getName(), CmsBlocks::all());

        $this->assertContains('race_widget', $names);
    }
}
