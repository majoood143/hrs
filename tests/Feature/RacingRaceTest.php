<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesRacingSource;
use Tests\TestCase;

class RacingRaceTest extends TestCase
{
    use FakesRacingSource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareRacingSite();
        $this->fakeRacingSource();
    }

    public function test_results_page_shows_the_race_the_table_and_links_everything_together(): void
    {
        $this->get('/racing/results/9178')
            ->assertOk()
            ->assertSee('Race Results')
            ->assertSee('Al Rahba , Friday, 5th Meeting-National Day Cups, 19 Nov 2021')
            ->assertSee('National Museum 51st National Day Cup')
            ->assertSee('1:51:15')
            ->assertSee('https://www.youtube.com/watch?v=1DFTS1ySgIY', false)
            // horses, trainers, jockeys and owners inside the table all link to their own pages
            ->assertSee('/racing/horse/3216', false)
            ->assertSee('/racing/trainer/176', false)
            ->assertSee('/racing/jockey/88', false)
            ->assertSee('/racing/owner/140', false)
            ->assertSee('Overweights')
            // every race in the meeting is a tab (its own URL), and the sibling pages keep this race
            ->assertSee('/racing/results/9172', false)
            ->assertSee('/racing/entries/9178', false)
            ->assertSee('/racing/card/9178', false)
            ->assertSee('/racing/form-guide/9178', false)
            ->assertSee('/racing/handicap-ratings', false)
            ->assertSee('/racing/search', false);
    }

    public function test_the_selected_tab_is_marked_current(): void
    {
        $html = $this->get('/racing/results/9172')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#href="[^"]*/racing/results/9172"[^>]*aria-current="page"#', $html);
        $this->assertDoesNotMatchRegularExpression('#href="[^"]*/racing/results/9178"[^>]*aria-current#', $html);
    }

    public function test_entries_page(): void
    {
        $this->get('/racing/entries/9178')
            ->assertOk()
            ->assertSee('Race Entries')
            ->assertSee('S.s.r. Alfaseeh (OM)')
            ->assertSee('/racing/owner/681', false)
            ->assertSee('/racing/trainer/53', false);
    }

    public function test_race_card_page(): void
    {
        $this->get('/racing/card/9178')
            ->assertOk()
            ->assertSee('Race Card')
            ->assertSee('S.s.r. Alfaseeh (OM)')
            ->assertSee('27 (7-5-4)')
            ->assertSee('(OR 83)')
            ->assertSee('/racing/jockey/26', false)
            // owner silks are served through our own domain, never the source's http:// URL
            ->assertSee('/racing/image?p=', false)
            ->assertDontSee('185.64.25.43');
    }

    public function test_form_guide_page(): void
    {
        $this->get('/racing/form-guide/9178')
            ->assertOk()
            ->assertSee('Form Guide')
            ->assertSee('Beaten By')
            ->assertSee('title="Length: 12.1 Wgt: 58.0"', false)
            ->assertSee('Z.F. Alsoroor (OM)');
    }

    public function test_default_pages_show_the_sources_latest_meeting(): void
    {
        $this->get('/racing/results')
            ->assertOk()
            ->assertSee('Al Rahba , Saturday, Final Meeting, 18 Apr 2026')
            // no race carried, so the nav points at the latest pages, not a fixed race
            ->assertSee('href="' . url('/racing/entries') . '"', false);

        $this->assertSame(1, $this->sentCount('page=RaceResults'));
    }

    public function test_upcoming_meeting_without_declarations_says_so(): void
    {
        $this->get('/racing/entries')
            ->assertOk()
            ->assertSee('Al Rahba , Saturday, 1st Meeting, 17 Oct 2026')
            ->assertSee('3YO Maiden Plate')
            ->assertSee('Entries are not available for this race.');
    }

    public function test_an_unknown_race_is_a_404_and_never_reaches_the_slow_pages(): void
    {
        foreach (['results', 'entries', 'card', 'form-guide'] as $page) {
            $this->get("/racing/{$page}/99999999")->assertNotFound();
        }

        // the source hangs for a minute on unknown ids for card / form guide, so only the cheap check may run
        $this->assertSame(0, $this->sentCount('page=Race'));
        $this->assertSame(0, $this->sentCount('Details.cfm'));
        $this->assertGreaterThan(0, $this->sentCount('method=getRaceLink'));
    }

    public function test_bad_urls_are_404_without_calling_the_source(): void
    {
        Http::fake();

        $this->get('/racing/results/abc')->assertNotFound();
        $this->get('/racing/summary/9178')->assertNotFound();
        $this->get('/racing/race/abc')->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_switching_race_tabs_reuses_the_cached_meeting(): void
    {
        $this->get('/racing/results/9178')->assertOk();
        $this->get('/racing/results/9172')->assertOk()->assertSee('National Museum 51st National Day Cup'); // fixture serves one meeting shell

        $this->assertSame(1, $this->sentCount('page=RaceResults'), 'one shell fetch covers every race in the meeting');
        $this->assertSame(2, $this->sentCount('RaceResultDetails.cfm'), 'but each race loads its own results');
    }

    public function test_pages_are_cached(): void
    {
        $this->get('/racing/card/9178')->assertOk();
        $this->get('/racing/card/9178')->assertOk();

        $this->assertSame(1, $this->sentCount('RaceCardDetails.cfm'));
        $this->assertSame(1, $this->sentCount('method=getRaceLink'));
    }

    public function test_bare_race_links_redirect_to_the_right_page(): void
    {
        $this->get('/racing/race/9178')->assertRedirect('/racing/results/9178');
        $this->get('/racing/race/10005')->assertRedirect('/racing/entries/10005'); // not run yet
        $this->get('/racing/race/99999999')->assertNotFound();
    }

    public function test_arabic_pages_are_requested_in_arabic_and_rendered_rtl(): void
    {
        $this->get('/racing/results/9178?lang=ar')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('نتائج السباق')
            ->assertSee('متحف بيت البرندة');

        $this->assertGreaterThan(0, $this->sentCount('lang=ar'));
    }

    public function test_a_source_outage_is_a_friendly_message_not_a_500(): void
    {
        $this->replaceRacingFake(fn () => throw new ConnectionException('timeout'));

        foreach (['/racing/results', '/racing/entries/9178', '/racing/card/9178', '/racing/form-guide/9178'] as $url) {
            $this->get($url)->assertOk()->assertSee('temporarily unavailable');
        }

        $this->get('/racing/race/9178')->assertOk()->assertSee('temporarily unavailable');
    }

    public function test_an_upstream_error_status_is_handled_too(): void
    {
        $this->replaceRacingFake(['*' => Http::response('boom', 500)]);

        $this->get('/racing/results')->assertOk()->assertSee('temporarily unavailable');
    }

    public function test_horse_profile_race_names_link_to_the_race_page(): void
    {
        $this->replaceRacingFake(['*' => Http::response($this->fixture('horse.html'))]);

        $this->get('/racing/horse/1205')
            ->assertOk()
            ->assertSee('/racing/race/9178', false)
            ->assertSee('/racing/race/8927', false);
    }
}
