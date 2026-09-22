<?php

namespace Tests\Concerns;

use App\Models\SiteSetting;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A stand-in for the racing source, answering from real captured responses in tests/Fixtures/racing.
 *
 * The project's migrations are MySQL-only, so instead of RefreshDatabase we give the site layout and
 * the 404 fallback just what they read: empty site settings, menus and redirects.
 */
trait FakesRacingSource
{
    use PreparesSiteLayout;

    protected function prepareRacingSite(): void
    {
        $this->prepareSiteLayout();

        config(['racing.base_url' => 'http://racing.test']);
    }

    protected function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/racing/{$name}"));
    }

    /**
     * Race 9178 (and its meeting siblings) are a past meeting with full data; 10005 is an upcoming one
     * with nothing declared yet; 99999999 does not exist.
     */
    protected function fakeRacingSource(): void
    {
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            $path = parse_url($request->url(), PHP_URL_PATH);
            $ar = ($q['lang'] ?? 'en') === 'ar';

            if ($path === '/components/race.cfc') {
                return match ($q['method'] ?? null) {
                    'getRaceLink' => Http::response($this->fixture(match ((int) $q['raceID']) {
                        99999999 => 'raceinfo_missing.json',
                        10005 => 'raceinfo_10005.json',
                        default => 'raceinfo_9178.json',
                    })),
                    'getHandicapRatings' => Http::response($this->fixture('handicap.json')),
                    'getRaceDetailsWeb' => Http::response(match (true) {
                        // one day's races (the widget) vs the race dates of a season (the calendar)
                        ($q['dateFrom'] ?? null) === ($q['dateTo'] ?? 0) => match ($q['dateFrom']) {
                            '2026-10-17', '2026-04-18', '2026-04-02' => $this->fixture("day_{$q['dateFrom']}.json"),
                            default => '{"TOTAL":0,"RECORDS":0,"PAGE":0,"ROWS":""}',
                        },
                        default => match ($q['dateFrom'] ?? null) {
                            '2026-10-01' => $this->fixture('calendar_days_2627.json'),
                            '2025-10-01' => $this->fixture('calendar_days_2526.json'),
                            default => '[]', // the source's answer for a season with no meetings
                        },
                    }),
                    'getRaceLinkByDate' => Http::response(match ($q['raceDate'] ?? null) {
                        '2026-10-17' => $this->fixture('calendar_link_entries.json'),
                        '2026-04-18' => $this->fixture('calendar_link_results.json'),
                        default => $this->fixture('calendar_link_none.json'),
                    }),
                    default => Http::response('nope', 404),
                };
            }

            if (str_starts_with($path, '/pages/') && preg_match('#/(Race(Result|Entry|Card|FormGuide))Details\.cfm$#', $path, $m)) {
                $page = ['Result' => 'results', 'Entry' => 'entries', 'Card' => 'card', 'FormGuide' => 'form-guide'][$m[2]];

                return Http::response($this->fixture((int) $q['raceID'] === 10005 ? 'fragment_unavailable.html' : "fragment_{$page}_9178.html"));
            }

            // profiles: horse 1205 exists (English / Arabic), every other horse does not; owners share one page
            if (preg_match('#/pages/(Horse|Owner|Jockey|Trainer)_GenWin\.cfm$#', $path, $m)) {
                return Http::response($this->fixture(match (true) {
                    $m[1] === 'Owner' => 'owner.html',
                    $m[1] === 'Jockey' => 'jockey.html',
                    $m[1] === 'Trainer' => 'trainer.html',
                    (int) ($q['horseID'] ?? 0) === 1205 => $ar ? 'horse_ar.html' : 'horse.html',
                    default => 'horse_missing.html',
                }));
            }

            if (str_starts_with($path, '/img/')) {
                return Http::response('JPEG', 200, ['Content-Type' => 'image/jpeg']);
            }

            if ($path === '/' && ($q['page'] ?? null) === 'RaceCalendar') {
                return Http::response($this->fixture('calendar_page.html'));
            }

            if ($path === '/' && isset($q['page'])) {
                $page = ['RaceResults' => 'results', 'RaceEntry' => 'entries', 'RaceCard' => 'card', 'RaceFormGuide' => 'form'][$q['page']] ?? null;
                $latest = ! isset($q['racefile']);

                return $page === null ? Http::response('nope', 404) : Http::response($this->fixture(match (true) {
                    $page === 'results' && $ar => 'meeting_results_9178_ar.html',
                    $latest && $page === 'results' => 'meeting_results_latest.html',
                    $latest => 'meeting_entries_latest.html',
                    default => "meeting_{$page}_9178.html",
                }));
            }

            return Http::response('nope', 404);
        });
    }

    /**
     * Http::fake() calls stack (the first stub to answer wins), so to override the source mid-test
     * we swap in a fresh factory and drop anything cached from earlier requests.
     */
    protected function replaceRacingFake(mixed $fake): void
    {
        Http::swap(new Factory);
        Http::fake($fake);

        Cache::flush();
        Cache::forever('site_settings.all', collect());
        SiteSetting::resetMemo();
    }

    /** How many requests hit the source's page/method with the given query fragment, e.g. "page=RaceResults". */
    protected function sentCount(string $needle): int
    {
        return Http::recorded(fn (Request $r) => str_contains(urldecode($r->url()), $needle))->count();
    }
}
