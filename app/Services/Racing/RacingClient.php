<?php

namespace App\Services\Racing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RacingClient
{
    /** Profile entity => [detail page on the source, id parameter]. */
    public const ENTITIES = [
        'horse' => ['Horse_GenWin', 'horseID'],
        'owner' => ['Owner_GenWin', 'ownerID'],
        'jockey' => ['Jockey_GenWin', 'jockeyID'],
        'trainer' => ['Trainer_GenWin', 'trainerID'],
    ];

    /** Race-day page => [meeting shell page on the source, per-race fragment]. */
    public const RACE_PAGES = [
        'results' => ['RaceResults', 'RaceResultDetails'],
        'entries' => ['RaceEntry', 'RaceEntryDetails'],
        'card' => ['RaceCard', 'RaceCardDetails'],
        'form-guide' => ['RaceFormGuide', 'RaceFormGuideDetails'],
    ];

    public function __construct(
        private readonly RacingParser $parser,
        private readonly RacingMeetingParser $meetingParser,
        private readonly RacingHandicapParser $handicapParser,
        private readonly RacingCalendarParser $calendarParser,
    ) {
    }

    /**
     * @return array<string, array{headers: list<string>, rows: list<array{id: int, cells: list<string>}>}>
     *
     * @throws RacingUnavailableException
     */
    public function search(string $query, RacingSearchType $type, string $locale): array
    {
        $key = sprintf('racing:search:%d:%s:%s', $type->value, $this->lang($locale), md5(mb_strtolower($query)));

        return Cache::remember($key, config('racing.cache_ttl'), fn () => $this->parser->parseSearch(
            $this->get('/', ['page' => 'Search', 'QRECsearch' => $query, 'type' => $type->value], $locale)
        ));
    }

    /**
     * @return ?array<string, mixed> see RacingParser::parseProfile(); null when the record does not exist
     *
     * @throws RacingUnavailableException
     */
    public function profile(string $entity, int $id, string $locale): ?array
    {
        [$page, $param] = self::ENTITIES[$entity];

        $key = sprintf('racing:profile:%s:%d:%s', $entity, $id, $this->lang($locale));

        return Cache::remember($key, config('racing.cache_ttl'), fn () => $this->parser->parseProfile(
            $this->get("/pages/{$page}.cfm", ['type' => 'genInfo', $param => $id, 'racePageLink' => 'RaceResults'], $locale)
        ));
    }

    /**
     * Does this race exist, and where would the source send you for it?
     *
     * This is the only safe way to vet a race id: the source answers an unknown id on the race card /
     * form guide pages with a 9–10 MB page that takes over a minute, so those pages are never requested
     * for an id that has not passed this cheap check first.
     *
     * @return array{exists: bool, entries: int, results: int, declarations: int, page: string}
     *
     * @throws RacingUnavailableException
     */
    public function raceInfo(int $raceId): array
    {
        return Cache::remember("racing:raceinfo:{$raceId}", config('racing.cache_ttl'), function () use ($raceId) {
            $data = json_decode(trim($this->get('/components/race.cfc', ['method' => 'getRaceLink', 'raceID' => $raceId])), true);

            if (! is_array($data)) {
                throw new RacingUnavailableException('Racing source returned an unexpected race response.');
            }

            $page = (string) ($data['PAGE'] ?? '');

            return [
                'exists' => preg_match('/raceFile=(\d+)/i', $page, $m) === 1 && (int) $m[1] > 0,
                'entries' => (int) ($data['TOTALENTRIES'] ?? 0),
                'results' => (int) ($data['TOTALRACERESULTS'] ?? 0),
                'declarations' => (int) ($data['TOTALDECLARATIONS'] ?? 0),
                'page' => $page,
            ];
        });
    }

    /**
     * The meeting (header + every race in it) a race belongs to; with no id, the source's default
     * meeting for that page (latest results / next entries). Never call with an unvetted id.
     *
     * @param  'results'|'entries'|'card'|'form-guide'  $page
     * @return ?array<string, mixed> see RacingMeetingParser::parseMeeting()
     *
     * @throws RacingUnavailableException
     */
    public function meeting(string $page, ?int $raceId, string $locale): ?array
    {
        $lang = $this->lang($locale);
        $key = fn (int|string $id) => "racing:meeting:{$page}:{$id}:{$lang}";

        if ($cached = Cache::get($key($raceId ?? 'default'))) {
            return $cached;
        }

        $meeting = $this->meetingParser->parseMeeting($this->get('/', array_filter([
            'page' => self::RACE_PAGES[$page][0],
            'racefile' => $raceId,
            'showIndexLink' => 'false',
        ], fn ($v) => $v !== null), $locale));

        if ($meeting !== null) {
            // every race in the meeting shares this shell, so switching race tabs costs nothing
            Cache::put($key($raceId ?? 'default'), $meeting, config('racing.cache_ttl'));

            foreach ($meeting['races'] as $race) {
                Cache::put($key($race['id']), $meeting, config('racing.cache_ttl'));
            }
        }

        return $meeting;
    }

    /**
     * One race's results / entries / race card / form guide.
     *
     * @param  'results'|'entries'|'card'|'form-guide'  $page
     * @return array<string, mixed> see RacingMeetingParser::parseFragment()
     *
     * @throws RacingUnavailableException
     */
    public function raceDetail(string $page, int $raceId, string $locale): array
    {
        $key = sprintf('racing:race:%s:%d:%s', $page, $raceId, $this->lang($locale));

        return Cache::remember($key, config('racing.cache_ttl'), fn () => $this->meetingParser->parseFragment(
            $page,
            $this->get('/pages/' . self::RACE_PAGES[$page][1] . '.cfm', ['raceID' => $raceId, 'showIndexLink' => 'false', 'returnOption' => 'true'], $locale)
        ));
    }

    /**
     * Every horse for a breed / location filter ('' / TB / PA and 1 / 2 / 3), in the source's order.
     *
     * @return list<array{id: int, name: string, rating: ?int, label: ?string, change: ?int, rating_date: ?string, last_ran: ?string}>
     *
     * @throws RacingUnavailableException
     */
    public function handicap(string $breed, string $location, string $locale): array
    {
        return Cache::remember($this->handicapKey($breed, $location, $locale), config('racing.handicap_ttl'), fn () => $this->fetchHandicap($breed, $location, $locale));
    }

    /**
     * Re-fetch a handicap list and swap it into the cache in one step (no cold window for visitors),
     * for the scheduled `racing:warm` command.
     *
     * @throws RacingUnavailableException
     */
    public function refreshHandicap(string $breed, string $location, string $locale): int
    {
        $horses = $this->fetchHandicap($breed, $location, $locale);

        Cache::put($this->handicapKey($breed, $location, $locale), $horses, config('racing.handicap_ttl'));

        return count($horses);
    }

    private function handicapKey(string $breed, string $location, string $locale): string
    {
        return sprintf('racing:handicap:%s:%s:%s', $breed ?: 'all', $location, $this->lang($locale));
    }

    /** @throws RacingUnavailableException */
    private function fetchHandicap(string $breed, string $location, string $locale): array
    {
        return $this->handicapParser->parse($this->get('/components/race.cfc', [
            'method' => 'getHandicapRatings',
            'jqgrid' => 'true',
            'horseName' => '',
            'horseBreed' => $breed,
            'horseLoc' => $location,
            'rows' => 10000,
            'page' => 1,
        ], $locale, config('racing.handicap_timeout')));
    }

    /**
     * Which of our race-day pages a source link (e.g. "?page=RaceEntry&raceFile=10005") stands for.
     *
     * @return 'results'|'entries'|'card'|'form-guide'
     */
    public static function pageKey(string $sourcePage): string
    {
        return match (true) {
            stripos($sourcePage, 'raceentry') !== false => 'entries',
            stripos($sourcePage, 'racecard') !== false => 'card',
            stripos($sourcePage, 'raceformguide') !== false => 'form-guide',
            default => 'results',
        };
    }

    /**
     * A season runs 1 October to 30 September: "25/26" => 2025-10-01 .. 2026-09-30.
     *
     * @return array{from: string, to: string}
     */
    public static function seasonRange(string $season): array
    {
        $yy = (int) substr($season, 0, 2);
        $year = $yy > 50 ? 1900 + $yy : 2000 + $yy;

        return ['from' => "{$year}-10-01", 'to' => ($year + 1) . '-09-30'];
    }

    /** The season a date belongs to: October to December start it, January to September finish it. */
    public static function seasonOf(string $date): string
    {
        [$year, $month] = array_map('intval', explode('-', $date));
        $start = $month >= 10 ? $year : $year - 1;

        return sprintf('%02d/%02d', $start % 100, ($start + 1) % 100);
    }

    /**
     * The seasons the calendar offers, newest first.
     *
     * @return list<string>
     *
     * @throws RacingUnavailableException
     */
    public function seasons(): array
    {
        return Cache::remember('racing:calendar:seasons', 86400, function () {
            $seasons = $this->calendarParser->parseSeasons($this->get('/', ['page' => 'RaceCalendar', 'showIndexLink' => 'false']));

            if ($seasons !== []) {
                return $seasons;
            }

            // the season list is a <select> on the source's page; if that markup ever changes, still offer
            // every season back to the earliest data (2011/12) rather than a broken calendar
            $latest = (int) date('n') >= 9 ? (int) date('Y') : (int) date('Y') - 1;

            return array_map(fn (int $y) => sprintf('%02d/%02d', $y % 100, ($y + 1) % 100), range($latest, 2011));
        });
    }

    /**
     * Every race day of a season. The season must come from seasons(): the source answers a malformed
     * date range with every race it has ever run (98 KB, ~11 s), so only dates we build are ever sent.
     *
     * @return array<string, ?string> Y-m-d => meeting name (English only, the source does not translate it)
     *
     * @throws RacingUnavailableException
     */
    public function raceDays(string $season): array
    {
        if (preg_match('#^\d{2}/\d{2}$#', $season) !== 1) {
            throw new \InvalidArgumentException("Not a season: {$season}");
        }

        ['from' => $from, 'to' => $to] = self::seasonRange($season);

        return Cache::remember("racing:calendar:days:{$from}", config('racing.calendar_ttl'), fn () => $this->calendarParser->parseDays(
            $this->get('/components/race.cfc', ['method' => 'getRaceDetailsWeb', 'returnType' => 'raceDates', 'dateFrom' => $from, 'dateTo' => $to])
        ));
    }

    /**
     * The races run (or scheduled) on one day, with which of their pages / video / photo exist. The date
     * must be one raceDays() lists. The source pages this call three rows at a time unless `rows` is
     * given, so a generous page size is always sent.
     *
     * @return list<array<string, mixed>> see RacingCalendarParser::parseRaces()
     *
     * @throws RacingUnavailableException
     */
    public function raceDay(string $date, string $locale): array
    {
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $date, $m) !== 1 || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new \InvalidArgumentException("Not a date: {$date}");
        }

        // a race day that is over never changes again, so the archive is cached far longer than today / upcoming days
        $ttl = $date < now()->format('Y-m-d') ? config('racing.past_day_ttl') : config('racing.cache_ttl');

        return Cache::remember("racing:calendar:races:{$date}:{$this->lang($locale)}", $ttl, fn () => $this->calendarParser->parseRaces(
            $this->get('/components/race.cfc', ['method' => 'getRaceDetailsWeb', 'dateFrom' => $date, 'dateTo' => $date, 'rows' => 100, 'page' => 1], $locale)
        ));
    }

    /**
     * Where the source sends a click on a calendar date: the results of that meeting's first race once it
     * has run, its entries before that. Only call for a date that raceDays() lists.
     *
     * @return ?array{page: string, race: int}
     *
     * @throws RacingUnavailableException
     */
    public function raceLinkByDate(string $date): ?array
    {
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $date, $m) !== 1 || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return Cache::remember("racing:calendar:link:{$date}", config('racing.calendar_ttl'), fn () => $this->calendarParser->parseLink(
            $this->get('/components/race.cfc', ['method' => 'getRaceLinkByDate', 'raceDate' => $date])
        ));
    }

    /**
     * Fetch an image (owner silks, ...) so the browser never needs the source's http:// URL.
     * Only /img/* paths are accepted.
     *
     * @return ?array{body: string, type: string}
     */
    public function image(string $path): ?array
    {
        if (! preg_match('#^/img/[\w\s./()-]+\.(jpe?g|png|gif)$#i', $path) || str_contains($path, '..')) {
            return null;
        }

        $response = $this->request()->get($path);

        return $response->successful() && str_starts_with((string) $response->header('Content-Type'), 'image/')
            ? ['body' => $response->body(), 'type' => $response->header('Content-Type')]
            : null;
    }

    /** The source keeps language in its session, so it is sent explicitly on every call. */
    private function lang(string $locale): string
    {
        return $locale === 'ar' ? 'ar' : 'en';
    }

    private function request(?int $timeout = null): PendingRequest
    {
        return Http::baseUrl(config('racing.base_url'))
            ->timeout($timeout ?? config('racing.timeout'))
            ->retry(1, 250, throw: false)
            ->withUserAgent('HRS-Website/1.0');
    }

    /** @throws RacingUnavailableException */
    private function get(string $path, array $query, ?string $locale = null, ?int $timeout = null): string
    {
        try {
            $response = $this->request($timeout)->get($path, $locale === null ? $query : $query + ['lang' => $this->lang($locale)]);
        } catch (ConnectionException $e) {
            throw new RacingUnavailableException('Racing source unreachable: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new RacingUnavailableException('Racing source responded with HTTP ' . $response->status());
        }

        return $response->body();
    }
}
