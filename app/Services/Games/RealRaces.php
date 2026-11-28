<?php

namespace App\Services\Games;

use App\Models\GameCampaign;
use App\Models\GameRace;
use App\Services\Racing\RacingClient;
use App\Services\Racing\RacingUnavailableException;
use Illuminate\Support\Carbon;

/**
 * Real races for game campaigns, read through the website's racing integration (RacingClient, the
 * racing club's site): the race days to pick from, the races of a day, and a race's declared field
 * (race card, English and Arabic) plus its official result once run. A race is copied into the
 * campaign, so a campaign keeps working when the source is down; "refresh" reads it again.
 */
class RealRaces
{
    public function __construct(private readonly RacingClient $racing) {}

    /**
     * Race days of this season and the last one, newest first: Y-m-d => "19 Nov 2026 · meeting".
     *
     * @return array<string, string>
     *
     * @throws RacingUnavailableException
     */
    public function raceDays(): array
    {
        $today = now()->format('Y-m-d');
        $seasons = array_unique([RacingClient::seasonOf($today), RacingClient::seasonOf(now()->subYear()->format('Y-m-d'))]);
        $days = [];

        foreach ($seasons as $season) {
            if (in_array($season, $this->racing->seasons(), true)) {
                $days += $this->racing->raceDays($season);
            }
        }

        krsort($days);
        $labels = [];

        foreach ($days as $date => $meeting) {
            $labels[$date] = Carbon::parse($date)->translatedFormat('j M Y').($meeting ? ' · '.$meeting : '').($date >= $today ? ' · '.__('games.admin.upcoming') : '');
        }

        return $labels;
    }

    /**
     * The races of a day whose field is declared (a race card or a result): id => "15:30 · name · 2000 m".
     *
     * @return array<int, string>
     *
     * @throws RacingUnavailableException
     */
    public function racesOn(string $date): array
    {
        $races = [];

        foreach ($this->racing->raceDay($date, app()->getLocale()) as $race) {
            if ($race['card'] || $race['results']) {
                $races[$race['id']] = implode(' · ', array_filter([$race['time'], $race['name'], $race['distance'] ? $race['distance'].' m' : null]));
            }
        }

        return $races;
    }

    /**
     * Add a race to a campaign, or read it again (a changed field, the result once run).
     *
     * @throws GameException when the race has no declared field the game can run
     * @throws RacingUnavailableException
     */
    public function import(GameCampaign $campaign, int $sourceRaceId, string $date): GameRace
    {
        $info = $this->racing->raceInfo($sourceRaceId);
        if (! $info['exists']) {
            throw new GameException('race_not_found');
        }

        $run = $info['results'] > 0;
        $page = $run ? 'results' : 'card';
        $summary = ['en' => $this->summary($page, $sourceRaceId, 'en'), 'ar' => $this->summary($page, $sourceRaceId, 'ar')];
        $cards = ['en' => $this->racing->raceDetail('card', $sourceRaceId, 'en'), 'ar' => $this->racing->raceDetail('card', $sourceRaceId, 'ar')];

        if (! ($cards['en']['available'] ?? false) || ($cards['en']['runners'] ?? []) === []) {
            throw new GameException('field_not_declared');
        }

        $results = $run ? $this->positions($this->racing->raceDetail('results', $sourceRaceId, 'en')) : [];
        $existing = GameRace::query()->where('game_campaign_id', $campaign->id)->where('source_race_id', $sourceRaceId)->first();
        $runners = $this->keepHandEdits($existing, $this->runners($cards, $results));

        $this->checkField($runners);

        $distance = (int) preg_replace('/\D/', '', (string) ($summary['en']['info'][1] ?? ''));
        if ($distance < 800 || $distance > 4000) {
            throw new GameException('race_not_found');
        }

        return GameRace::query()->updateOrCreate(
            ['game_campaign_id' => $campaign->id, 'source_race_id' => $sourceRaceId],
            [
                'source' => GameRace::SOURCE_CLUB,
                'race_date' => $date,
                'post_time' => preg_match('/^\d{1,2}:\d{2}$/', (string) ($summary['en']['info'][0] ?? '')) ? $summary['en']['info'][0] : null,
                'en_name' => (string) ($summary['en']['title'] ?? 'Race '.$sourceRaceId),
                'ar_name' => (string) (($summary['ar']['title'] ?? null) ?: ($summary['en']['title'] ?? 'Race '.$sourceRaceId)),
                'en_meeting' => $summary['en']['heading'] ?? null,
                'ar_meeting' => $summary['ar']['heading'] ?? null,
                'distance' => $distance,
                'en_surface' => $summary['en']['facts'][0]['value'] ?? null,
                'ar_surface' => $summary['ar']['facts'][0]['value'] ?? null,
                'runners' => $runners,
                'winning_time_ms' => $run ? self::runningTimeMs($summary['en']['running_time']['value'] ?? null) : null,
                'has_result' => $run && $results !== [],
                'refreshed_at' => now(),
            ],
        );
    }

    /**
     * Save a race typed or corrected by hand. A new or manual race takes the race's own details too; on
     * a club race only the field changes, and every runner changed, added or taken out is remembered so
     * that "Refresh field" leaves it so.
     *
     * @param  array<string, mixed>  $data  the admin form: runners, plus the race's details for a manual race
     *
     * @throws GameException when the field does not suit the game
     */
    public function saveByHand(GameCampaign $campaign, ?GameRace $race, array $data): GameRace
    {
        $runners = array_values(array_map(fn (array $r) => self::cleanRunner($r), (array) ($data['runners'] ?? [])));
        $this->checkField($runners);

        $race ??= new GameRace(['game_campaign_id' => $campaign->id, 'source' => GameRace::SOURCE_MANUAL]);

        if ($race->isManual()) {
            $race->fill([
                'race_date' => $data['race_date'],
                'post_time' => $data['post_time'] ?: null,
                'en_name' => trim((string) $data['en_name']),
                'ar_name' => trim((string) ($data['ar_name'] ?: $data['en_name'])),
                'distance' => (int) $data['distance'],
                'en_surface' => $data['en_surface'] ?: null,
                'ar_surface' => ($data['ar_surface'] ?? null) ?: ($data['en_surface'] ?: null),
                'winning_time_ms' => self::runningTimeMs($data['winning_time'] ?? null),
            ]);
        } else {
            // what the admin changed against what was there: those stay as they are on every refresh
            $before = collect($race->runners)->keyBy(fn ($r) => (string) $r['no']);
            $after = collect($runners)->keyBy(fn ($r) => (string) $r['no']);
            $changed = $after->filter(fn ($r, $no) => ! $before->has($no) || self::cleanRunner($before[$no]) != $r)->keys()->all();
            $removed = $before->keys()->diff($after->keys())->all();

            $race->locked_nos = array_values(array_unique(array_diff([...($race->locked_nos ?? []), ...$changed], $removed)));
            $race->removed_nos = array_values(array_unique(array_diff([...($race->removed_nos ?? []), ...$removed], $after->keys()->all())));
        }

        $race->runners = $runners;
        $race->has_result = collect($runners)->contains(fn ($r) => $r['result'] !== null);
        $race->refreshed_at ??= now();
        $race->save();

        return $race;
    }

    /**
     * A runner as stored, whatever the form sent: numbers as numbers, empty texts as null, both languages.
     *
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    public static function cleanRunner(array $r): array
    {
        $text = fn ($v) => is_string($v) && trim($v) !== '' ? trim($v) : null;
        $pair = function ($v) use ($text) {
            $en = $text($v['en'] ?? null);

            return $en === null && $text($v['ar'] ?? null) === null ? null : ['en' => $en ?? $text($v['ar'] ?? null), 'ar' => $text($v['ar'] ?? null) ?? $en];
        };
        $int = fn ($v) => is_numeric($v) ? (int) $v : null;
        $record = $r['record'] ?? null;
        $hasRecord = is_array($record) && collect(['starts', 'first', 'second', 'third'])->contains(fn ($k) => is_numeric($record[$k] ?? null));

        return [
            'no' => trim((string) ($r['no'] ?? '')),
            'gate' => $int($r['gate'] ?? null),
            'horse_id' => $int($r['horse_id'] ?? null),
            'name' => $pair($r['name'] ?? null) ?? ['en' => '', 'ar' => ''],
            'age' => $int($r['age'] ?? null),
            'colour' => $pair($r['colour'] ?? null),
            'sex' => $pair($r['sex'] ?? null),
            'record' => $hasRecord ? ['starts' => (int) ($record['starts'] ?? 0), 'first' => (int) ($record['first'] ?? 0), 'second' => (int) ($record['second'] ?? 0), 'third' => (int) ($record['third'] ?? 0)] : null,
            'owner' => $pair($r['owner'] ?? null),
            'trainer' => $pair($r['trainer'] ?? null),
            'jockey' => $pair($r['jockey'] ?? null),
            'weight' => is_numeric($r['weight'] ?? null) ? (float) $r['weight'] : null,
            'rating' => $int($r['rating'] ?? null),
            'silks' => $text($r['silks'] ?? null),
            'silks_upload' => $text(is_array($r['silks_upload'] ?? null) ? (array_values($r['silks_upload'])[0] ?? null) : ($r['silks_upload'] ?? null)),
            'result' => $int($r['result'] ?? null),
        ];
    }

    /** @param  list<array<string, mixed>>  $runners */
    private function checkField(array $runners): void
    {
        if (count($runners) < 2 || count($runners) > GameRace::MAX_RUNNERS) {
            throw new GameException('field_size', __('games.errors.field_size', ['max' => GameRace::MAX_RUNNERS]));
        }

        foreach (['no', 'gate'] as $key) {
            $values = array_filter(array_column($runners, $key), fn ($v) => $v !== null && $v !== '');
            if (count($values) !== count(array_unique($values))) {
                throw new GameException('duplicate_'.$key);
            }
        }
    }

    /**
     * The field as read from the club, with the admin's hand edits on top: runners changed or added by
     * hand stay as typed (taking the official place once the club has one), runners taken out stay out.
     *
     * @param  list<array<string, mixed>>  $imported
     * @return list<array<string, mixed>>
     */
    private function keepHandEdits(?GameRace $existing, array $imported): array
    {
        if (! $existing) {
            return $imported;
        }

        $locked = array_map('strval', $existing->locked_nos ?? []);
        $removed = array_map('strval', $existing->removed_nos ?? []);
        $mine = collect($existing->runners)->keyBy(fn ($r) => (string) $r['no']);
        $runners = [];

        foreach ($imported as $runner) {
            $no = (string) $runner['no'];
            if (in_array($no, $removed, true)) {
                continue;
            }
            if (in_array($no, $locked, true) && $mine->has($no)) {
                $runner = ['result' => $mine[$no]['result'] ?? $runner['result']] + $mine[$no];
            }
            $runners[] = $runner;
        }

        // added by hand: not in the club's field at all
        $seen = array_column($runners, 'no');
        foreach ($locked as $no) {
            if (! in_array($no, $seen, true) && $mine->has($no)) {
                $runners[] = $mine[$no];
            }
        }

        return $runners;
    }

    /** "1:20:85" (the source's minutes:seconds:hundredths) → 80850 ms. */
    public static function runningTimeMs(?string $value): ?int
    {
        if (! $value || preg_match('/^(\d+):(\d{1,2})[:.](\d{1,2})$/', trim($value), $m) !== 1) {
            return null;
        }

        return (int) $m[1] * 60000 + (int) $m[2] * 1000 + (int) str_pad($m[3], 2, '0') * 10;
    }

    /** @return array<string, mixed> the race's summary in the meeting (+ the meeting's heading) */
    private function summary(string $page, int $sourceRaceId, string $locale): array
    {
        $meeting = $this->racing->meeting($page, $sourceRaceId, $locale) ?? ['races' => []];

        foreach ($meeting['races'] as $race) {
            if ((int) $race['id'] === $sourceRaceId) {
                return $race + ['heading' => $meeting['heading'] ?? null];
            }
        }

        return [];
    }

    /** @return array<string, int> saddle-cloth number => official finishing place */
    private function positions(array $results): array
    {
        $headers = array_map('strtolower', $results['table']['headers'] ?? []);
        $place = array_search('fp', $headers, true);
        $no = array_search('no', $headers, true);
        $positions = [];

        foreach ($place === false || $no === false ? [] : ($results['table']['rows'] ?? []) as $row) {
            $position = (int) ($row[$place]['text'] ?? 0);
            if ($position > 0) {
                $positions[trim((string) $row[$no]['text'])] = $position;
            }
        }

        return $positions;
    }

    /**
     * @param  array{en: array, ar: array}  $cards
     * @param  array<string, int>  $results
     * @return list<array<string, mixed>>
     */
    private function runners(array $cards, array $results): array
    {
        $arabic = collect($cards['ar']['runners'] ?? [])->keyBy(fn ($r) => (string) $r['no']);
        $runners = [];

        foreach ($cards['en']['runners'] as $runner) {
            $no = (string) $runner['no'];

            // non-runners and reserves ("Reserve 2", no stall) never start; once run, the starters are the result's
            if (($runner['non_runner'] ?? false) || ! ctype_digit((string) ($runner['gate'] ?? '')) || ($results !== [] && ! isset($results[$no]))) {
                continue;
            }

            $ar = $arabic->get($no, []);
            $pair = fn (?string $en, ?string $arText) => ['en' => $en, 'ar' => $arText ?: $en];
            preg_match('/(\d+)\s*\((\d+)-(\d+)-(\d+)\)/', (string) ($runner['record'] ?? ''), $record);
            preg_match('/OR\s*(\d+)/i', (string) ($runner['weight_note'] ?? ''), $rating);

            $runners[] = [
                'no' => $no,
                'gate' => (int) ($runner['gate'] ?? 0) ?: null,
                'horse_id' => $runner['horse']['id'] ?? null,
                'name' => $pair($runner['horse']['name'] ?? $no, $ar['horse']['name'] ?? null),
                'age' => (int) ($runner['meta'][0] ?? 0) ?: null,
                'colour' => $pair($runner['meta'][1] ?? null, $ar['meta'][1] ?? null),
                'sex' => $pair($runner['meta'][2] ?? null, $ar['meta'][2] ?? null),
                'record' => $record ? ['starts' => (int) $record[1], 'first' => (int) $record[2], 'second' => (int) $record[3], 'third' => (int) $record[4]] : null,
                'owner' => $pair($runner['owner']['name'] ?? null, $ar['owner']['name'] ?? null),
                'trainer' => $pair($runner['trainer']['name'] ?? null, $ar['trainer']['name'] ?? null),
                'jockey' => $pair($runner['jockey']['name'] ?? null, $ar['jockey']['name'] ?? null),
                'weight' => is_numeric($runner['weight'] ?? null) ? (float) $runner['weight'] : null,
                'rating' => $rating ? (int) $rating[1] : null,
                'silks' => $runner['image'] ?? null,
                'result' => $results[$no] ?? null,
            ];
        }

        return $runners;
    }
}
