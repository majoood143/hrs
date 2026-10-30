<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesRacingSource;
use Tests\TestCase;

/**
 * Profiles' upcoming races: the source leaves its own tab empty until a race is run, so we fill it from the
 * race cards of the next race days (kept by `racing:warm --declared`).
 */
class RacingDeclaredRunsTest extends TestCase
{
    use FakesRacingSource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareRacingSite();

        // 17 Oct 2026 with its race cards out and nothing run yet: every race but 10005 gets race 9178's card
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);

            if (($q['method'] ?? null) !== 'getRaceDetailsWeb' || ($q['dateFrom'] ?? null) !== '2026-10-17' || ($q['dateTo'] ?? null) !== '2026-10-17') {
                return null;
            }

            return Http::response(preg_replace(
                "#<img src='img/icon_racecard_disabled_16.png'[^>]*>#",
                "<a class='raceLink' href='http://x/?page=RaceCard&racefile=1'><img src='img/icon_racecard_16.png'></a>",
                $this->fixture('day_2026-10-17.json'),
            ));
        });
        $this->fakeRacingSource();

        Carbon::setTestNow(Carbon::parse('2026-10-17 09:00', 'Asia/Muscat'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function upcomingTab(string $path, array $headers = []): array
    {
        $tabs = $this->getJson($path, $headers)->assertOk()->json('data.tabs');

        return $tabs[2];
    }

    public function test_a_declared_jockey_gets_their_upcoming_runs_without_the_profile_asking_for_cards(): void
    {
        $this->artisan('racing:warm --declared')
            ->expectsOutputToContain('Declared runners (en): 133 cached.')
            ->expectsOutputToContain('Declared runners (ar): 133 cached.')
            ->assertSuccessful();

        $cards = $this->sentCount('RaceCardDetails');
        $tab = $this->upcomingTab('/api/v1/racing/jockey/26');

        $this->assertSame($cards, $this->sentCount('RaceCardDetails'));
        $this->assertNull($tab['message']);
        $table = $tab['tables'][0];
        $this->assertSame(['Date', 'Time', 'Race', 'Horse', 'Trainer', 'Gate', 'Weight (kg)'], $table['headers']);
        // seven carded races (10005 has no card), all from the same faked card
        $this->assertCount(7, $table['rows']);
        $this->assertSame('17 Oct 26', $table['rows'][0][0]['text']);
        $this->assertSame(['type' => 'race', 'id' => 10030], $table['rows'][0][2]['link']);
        $this->assertSame(['type' => 'horse', 'id' => 3125], $table['rows'][0][3]['link']);
        $this->assertSame(['type' => 'trainer', 'id' => 53], $table['rows'][0][4]['link']);

        $ar = $this->upcomingTab('/api/v1/racing/jockey/26', ['Accept-Language' => 'ar']);
        $this->assertSame('الحصان', $ar['tables'][0]['headers'][3]);

        // the website's page shows the same rows
        $this->get('/racing/jockey/26')->assertOk()->assertSee(route('racing.race', 10030), false);
    }

    public function test_an_owner_sees_horse_and_jockey_columns(): void
    {
        $this->artisan('racing:warm --declared')->assertSuccessful();

        $table = $this->upcomingTab('/api/v1/racing/owner/681')['tables'][0];

        $this->assertSame(['Date', 'Time', 'Race', 'Horse', 'Jockey', 'Trainer', 'Gate', 'Weight (kg)'], $table['headers']);
        $this->assertSame(['type' => 'jockey', 'id' => 26], $table['rows'][0][4]['link']);
    }

    public function test_the_source_message_stays_when_nothing_is_declared_or_the_day_is_over(): void
    {
        // never warmed: the source's own tab
        $this->assertSame('There are no declared runners for this Jockey', $this->upcomingTab('/api/v1/racing/jockey/26')['message']);
        $this->assertSame(0, $this->sentCount('RaceCardDetails'));

        $this->artisan('racing:warm --declared')->assertSuccessful();

        // a jockey who is not on any card
        $this->assertSame([], $this->upcomingTab('/api/v1/racing/jockey/99')['tables']);

        // the next day the runs are gone (they are the source's results by then)
        Carbon::setTestNow(Carbon::parse('2026-10-18 09:00', 'Asia/Muscat'));
        $this->assertSame([], $this->upcomingTab('/api/v1/racing/jockey/26')['tables']);
    }
}
