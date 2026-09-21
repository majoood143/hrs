<?php

namespace App\Services\Racing;

use App\Services\Racing\Concerns\ParsesHtml;
use DOMElement;

/** Parses the racing calendar: the season list, the race days of a season, and a date's click-through link. */
class RacingCalendarParser
{
    use ParsesHtml;

    /** @return list<string> e.g. ['26/27', '25/26', ...] in the source's order (newest first) */
    public function parseSeasons(string $html): array
    {
        $seasons = [];

        foreach ($this->xpath($html)->query('//select[@name="season"]/option[@value]') as $option) {
            /** @var DOMElement $option */
            $value = trim($option->getAttribute('value'));

            if (preg_match('#^\d{2}/\d{2}$#', $value)) {
                $seasons[] = $value;
            }
        }

        return array_values(array_unique($seasons));
    }

    /**
     * {"RACES":{"45":[[2026,10,17],["1st Meeting"]], ...}} has one entry per race (the keys are just indexes),
     * so several entries share a date. An empty range comes back as `[]`.
     *
     * @return array<string, ?string> Y-m-d => meeting name (English, whatever the language), oldest first
     *
     * @throws RacingUnavailableException when the source answered with something other than that JSON
     */
    public function parseDays(string $body): array
    {
        $data = json_decode(trim($body), true);

        if (! is_array($data)) {
            throw new RacingUnavailableException('Racing source returned an unexpected calendar response.');
        }

        $days = [];

        foreach ((array) ($data['RACES'] ?? []) as $entry) {
            [$y, $m, $d] = array_map('intval', $entry[0] ?? [0, 0, 0]);

            if (! checkdate($m, $d, $y)) {
                continue;
            }

            $name = trim((string) ($entry[1][0] ?? ''));
            $days[sprintf('%04d-%02d-%02d', $y, $m, $d)] ??= $name !== '' ? $name : null;
        }

        ksort($days);

        return $days;
    }

    /**
     * One day's races, from the source's jqGrid JSON: {"TOTAL":1,"RECORDS":8,"ROWS":[[id, name, distance,
     * video, photo, entries, card, results, meeting, meeting post time, race post time], ...]}. The five
     * icon cells are HTML: a link when that item exists, a bare "…_disabled_16.png" image when it does not.
     * A day with no races comes back as "ROWS":"".
     *
     * @return list<array{id: int, name: string, distance: ?int, meeting: ?string, meeting_time: ?string, time: ?string, video: ?string, photo: ?string, entries: bool, card: bool, results: bool}>
     *
     * @throws RacingUnavailableException when the source answered with something other than that JSON
     */
    public function parseRaces(string $body): array
    {
        $data = json_decode(trim($body), true);

        if (! is_array($data) || ! array_key_exists('ROWS', $data)) {
            throw new RacingUnavailableException('Racing source returned an unexpected race-day response.');
        }

        $races = [];

        foreach (is_array($data['ROWS']) ? $data['ROWS'] : [] as $row) {
            if (! is_array($row) || (int) ($row[0] ?? 0) < 1) {
                continue;
            }

            $text = fn (int $i) => ($value = trim((string) ($row[$i] ?? ''))) !== '' ? $value : null;

            $races[] = [
                'id' => (int) $row[0],
                'name' => $text(1) ?? '',
                'distance' => isset($row[2]) && (int) $row[2] > 0 ? (int) $row[2] : null,
                'video' => $this->externalHref((string) ($row[3] ?? '')),
                'photo' => $this->externalHref((string) ($row[4] ?? '')),
                'entries' => $this->hasLink((string) ($row[5] ?? '')),
                'card' => $this->hasLink((string) ($row[6] ?? '')),
                'results' => $this->hasLink((string) ($row[7] ?? '')),
                'meeting' => $text(8),
                'meeting_time' => $text(9),
                'time' => $text(10),
            ];
        }

        return $races;
    }

    private function hasLink(string $cell): bool
    {
        return $this->href($cell) !== null;
    }

    private function href(string $cell): ?string
    {
        return preg_match('/<a\b[^>]*\bhref\s*=\s*([\'"])(.*?)\1/is', $cell, $m) === 1
            ? (trim(html_entity_decode($m[2])) ?: null)
            : null;
    }

    /**
     * The video / photo cells link straight out (a YouTube URL, some values carry a trailing "\r"), so the
     * link is only kept when it is a plain http(s) URL, and a relative one is resolved against the source.
     */
    private function externalHref(string $cell): ?string
    {
        $href = $this->href($cell);

        if ($href === null) {
            return null;
        }

        if (str_starts_with($href, '/')) {
            $href = rtrim((string) config('racing.base_url'), '/') . $href;
        }

        return preg_match('#^https?://[^\s<>"\']+$#i', $href) === 1 ? $href : null;
    }

    /**
     * {"PAGE":"?page=RaceResults&raceFile=9822", ...} for a race day, `""` for a day with no meeting.
     *
     * @return ?array{page: string, race: int} page = results|entries|card|form-guide; null when there is no meeting that day
     *
     * @throws RacingUnavailableException
     */
    public function parseLink(string $body): ?array
    {
        $data = json_decode(trim($body), true);

        if ($data === '' || $data === []) {
            return null;
        }

        if (! is_array($data)) {
            throw new RacingUnavailableException('Racing source returned an unexpected calendar link response.');
        }

        $page = (string) ($data['PAGE'] ?? '');

        if (preg_match('/raceFile=(\d+)/i', $page, $m) !== 1 || (int) $m[1] < 1) {
            return null;
        }

        return ['page' => RacingClient::pageKey($page), 'race' => (int) $m[1]];
    }
}
