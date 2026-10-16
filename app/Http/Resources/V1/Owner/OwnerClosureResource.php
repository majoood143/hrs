<?php

namespace App\Http\Resources\V1\Owner;

use App\Models\StableClosure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Days off: no times are made on them and open ones are taken down (times with bookings stay).
 *
 * @property-read StableClosure $resource
 */
class OwnerClosureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $closure = $this->resource;

        return [
            'id' => (int) $closure->id,
            /** @example 2026-10-20 */
            'starts_on' => $closure->starts_on->toDateString(),
            /** @example 2026-10-21 */
            'ends_on' => $closure->ends_on->toDateString(),
            /** Only this service is closed; null = the whole stable. */
            'offering' => $closure->stable_offering_id ? [
                'id' => (int) $closure->stable_offering_id,
                'name' => (string) $closure->offering?->name,
            ] : null,
            /** @var string|null */
            'reason' => $closure->reason,
        ];
    }
}
