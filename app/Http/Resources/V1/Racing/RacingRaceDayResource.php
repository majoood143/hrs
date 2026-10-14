<?php

namespace App\Http\Resources\V1\Racing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The races on one race day (the website's "Races" widget): time, distance, and which of the race's
 * pages exist yet (`pages`), plus its video / photo links when the source has them.
 *
 * @property array{date: string, races: list<array<string, mixed>>} $resource
 */
class RacingRaceDayResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $races = $this->resource['races'];

        return [
            'date' => $this->resource['date'],
            'meeting' => $races[0]['meeting'] ?? null,
            'meeting_time' => $races[0]['meeting_time'] ?? null,
            /** @var list<array{id: int, name: string, distance: ?int, time: ?string, video_url: ?string, photo_url: ?string, pages: array{entries: bool, card: bool, results: bool}}> */
            'races' => array_map(fn (array $race) => [
                'id' => (int) $race['id'],
                'name' => (string) $race['name'],
                'distance' => $race['distance'] ?? null,
                'time' => $race['time'] ?? null,
                'video_url' => $race['video'] ?? null,
                'photo_url' => $race['photo'] ?? null,
                'pages' => ['entries' => (bool) $race['entries'], 'card' => (bool) $race['card'], 'results' => (bool) $race['results']],
            ], $races),
            'web_url' => route('racing.calendar.day', $this->resource['date']),
        ];
    }
}
