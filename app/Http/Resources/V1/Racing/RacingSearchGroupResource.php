<?php

namespace App\Http\Resources\V1\Racing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One group of search results (horses, owners, jockeys or trainers): the source's columns and
 * rows, each row opening the profile `{group}/{id}`.
 *
 * @property array{group: string, headers: list<string>, rows: list<array{id: int, cells: list<string>}>, total: int} $resource
 */
class RacingSearchGroupResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            /** @var 'horse'|'owner'|'jockey'|'trainer' */
            'group' => $this->resource['group'],
            /** @var list<string> */
            'headers' => array_values($this->resource['headers']),
            /** @var list<array{id: int, cells: list<string>}> */
            'rows' => array_map(fn (array $row) => ['id' => (int) $row['id'], 'cells' => array_values($row['cells'])], $this->resource['rows']),
            /** Results in this group (rows may be a preview or one page of them). */
            'total' => (int) $this->resource['total'],
        ];
    }
}
