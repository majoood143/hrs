<?php

namespace App\Http\Resources\V1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A directory place card (stable, clinic, center or shop): what the website's listing shows.
 *
 * @property-read Model $resource
 */
class PlaceListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $place = $this->resource;
        $type = $place->getAttribute('type');

        return [
            'id' => (int) $place->getKey(),
            /** The detail endpoint's key. */
            'slug' => (string) $place->getAttribute('slug'),
            'name' => (string) $place->getAttribute('name'),
            /** Clinics, centers and shops have a type; stables none. */
            'type' => $type ? OptionResource::of((string) $type, (string) $place->getAttribute('type_label')) : null,
            'country' => LookupResource::make($this->whenLoaded('country')),
            'city' => LookupResource::make($this->whenLoaded('city')),
            'services' => LookupResource::collection($this->whenLoaded('services')),
            /** @var string|null */
            'cover_photo_url' => $place->getAttribute('cover_photo_url'),
            /** Shops only: sells online. */
            'is_online' => (bool) $place->getAttribute('is_online'),
        ];
    }
}
