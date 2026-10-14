<?php

namespace App\Http\Resources\V1\Racing;

use App\Support\Api\RacingData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A horse, owner, jockey or trainer from the racing database, as the website's profile page shows it.
 * Tables keep the source's columns; a cell's `link` opens another profile or a race.
 *
 * @property array<string, mixed> $resource RacingParser::parseProfile() + entity + id
 */
class RacingProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $profile = $this->resource;

        return [
            /** @var 'horse'|'owner'|'jockey'|'trainer' */
            'entity' => $profile['entity'],
            'id' => (int) $profile['id'],
            'title' => (string) $profile['title'],
            'image_url' => RacingData::image($profile['image'] ?? null),
            /** Age / colour / sex and similar, in the source's words. @var list<string> */
            'meta' => array_values($profile['meta'] ?? []),
            'days_since_run' => $profile['days_since_run'] ?? null,
            'pedigree' => $profile['pedigree'] ?? null,
            /** @var ?array{value: string, label: string} */
            'rating' => $profile['rating'] ?? null,
            /** @var list<array{label: string, value: string, link: ?array{type: string, id: int}}> */
            'facts' => array_map(fn (array $fact) => [
                'label' => $fact['label'],
                'value' => $fact['value'],
                'link' => RacingData::link($fact['link'] ?? null),
            ], $profile['facts'] ?? []),
            /** @var list<array{label: string, tables: list<array{headers: list<string>, rows: list<list<array{text: string, link: ?array{type: string, id: int}, title: ?string}>>}>, message: ?string}> */
            'tabs' => array_map(fn (array $tab) => [
                'label' => $tab['label'],
                'tables' => array_map(RacingData::table(...), $tab['tables']),
                'message' => $tab['message'] ?? null,
            ], $profile['tabs'] ?? []),
            'web_url' => route('racing.profile', [$profile['entity'], $profile['id']]),
        ];
    }
}
