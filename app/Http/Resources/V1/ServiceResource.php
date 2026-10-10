<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A directory service (what a stable/clinic/center/shop offers), for the `service_id` filter.
 *
 * @property-read int $id
 * @property-read string $name
 * @property-read string|null $applies_to
 */
class ServiceResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, applies_to: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            /** Which place type it is offered for: a type value, or `both`/`all`; null for stables. Show it when the chosen type matches. */
            'applies_to' => $this->resource->getAttribute('applies_to'),
        ];
    }
}
