<?php

namespace App\Http\Resources\V1\Racing;

use App\Support\Api\RacingData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A race day page (results, entries, race card or form guide): the meeting, every race in it (one
 * tab each), and the selected race's `detail`. `detail.available` is false when the source has
 * nothing for that race on that page yet (e.g. no results before the race).
 *
 * detail by page:
 * - results: `table` (+ `owners`, `overweights`)
 * - entries: `table`
 * - card: `runners` with `weight` / `weight_note`
 * - form-guide: `runners` with `runs` (their last races, a table)
 *
 * @property array{page: string, meeting: array<string, mixed>, selected: ?array<string, mixed>, detail: ?array<string, mixed>} $resource
 */
class RacingMeetingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        ['page' => $page, 'meeting' => $meeting, 'selected' => $selected, 'detail' => $detail] = $this->resource;

        return [
            /** @var 'results'|'entries'|'card'|'form-guide' */
            'page' => $page,
            'heading' => $meeting['heading'] ?? null,
            /** @var list<array{id: int, number: ?string, title: ?string, info: list<string>, facts: list<array{label: string, value: string}>, conditions: ?array{label: string, value: string}, prizes: list<array{place: string, amount: string}>, running_time: ?array{label: string, value: string}, video_url: ?string}> */
            'races' => array_map(fn (array $race) => [
                'id' => (int) $race['id'],
                'number' => $race['number'] ?? null,
                'title' => $race['title'] ?? null,
                'info' => array_values($race['info'] ?? []),
                'facts' => array_values($race['facts'] ?? []),
                'conditions' => $race['conditions'] ?? null,
                'prizes' => array_values($race['prizes'] ?? []),
                'running_time' => $race['running_time'] ?? null,
                'video_url' => $race['video_url'] ?? null,
            ], $meeting['races'] ?? []),
            /** The race the detail is for (null: the meeting has no races). */
            'race_id' => $selected ? (int) $selected['id'] : null,
            /** @var ?array<string, mixed> */
            'detail' => $detail === null ? null : $this->detail($page, $detail),
            'web_url' => route('racing.meeting', array_filter(['page' => $page, 'race' => $selected['id'] ?? null])),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(string $page, array $detail): array
    {
        if (! ($detail['available'] ?? false)) {
            return ['available' => false];
        }

        return ['available' => true] + match ($page) {
            'results' => [
                'table' => RacingData::table($detail['table']),
                'owners' => array_map(fn (array $o) => ['n' => $o['n'] ?? null, 'id' => (int) $o['id'], 'name' => $o['name']], $detail['owners'] ?? []),
                'overweights' => $detail['overweights'] ?? null,
            ],
            'entries' => ['table' => RacingData::table($detail['table'])],
            default => ['runners' => array_map(fn (array $runner) => RacingData::runner($runner, $page), $detail['runners'] ?? [])],
        };
    }
}
