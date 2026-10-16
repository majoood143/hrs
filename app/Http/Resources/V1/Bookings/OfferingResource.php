<?php

namespace App\Http\Resources\V1\Bookings;

use App\Http\Resources\V1\MoneyResource;
use App\Http\Resources\V1\OptionResource;
use App\Models\StableOffering;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A service a stable sells by the slot (a riding lesson…). The price is per rider.
 *
 * @property-read StableOffering $resource
 */
class OfferingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $offering = $this->resource;

        return [
            'id' => (int) $offering->id,
            'name' => (string) $offering->name,
            /** @var string|null */
            'description' => $offering->description,
            'type' => OptionResource::of($offering->type->value, $offering->type->label()),
            /** @var string|null */
            'photo_url' => $offering->photo_url,
            /** Per rider, before the service fee and VAT. */
            'price' => new MoneyResource($offering->price),
            'duration_minutes' => (int) $offering->duration_minutes,
            /** Riders per slot (1 = a private session). */
            'capacity' => (int) $offering->capacity,
            /** Riders one booking may bring. */
            'min_riders' => (int) $offering->min_riders,
            'max_riders' => (int) $offering->max_riders,
        ];
    }
}
