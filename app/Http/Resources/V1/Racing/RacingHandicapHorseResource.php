<?php

namespace App\Http\Resources\V1\Racing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One horse of the handicap ratings list (opens the horse profile by `id`).
 *
 * @property array{id: int, name: string, rating: ?int, label: ?string, change: ?int, rating_date: ?string, last_ran: ?string} $resource
 */
class RacingHandicapHorseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource['id'],
            'name' => (string) $this->resource['name'],
            'rating' => $this->resource['rating'],
            /** The source's rating label (e.g. Local / Non-Local; upstream, they look inverted). */
            'label' => $this->resource['label'],
            /** Change since the last rating. */
            'change' => $this->resource['change'],
            'rating_date' => $this->resource['rating_date'],
            'last_ran' => $this->resource['last_ran'],
        ];
    }
}
