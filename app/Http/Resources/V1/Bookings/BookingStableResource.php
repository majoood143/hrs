<?php

namespace App\Http\Resources\V1\Bookings;

use App\Http\Resources\V1\LookupResource;
use App\Models\Stable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A stable as the booking screens show it: enough to recognise it and find it.
 *
 * @property-read Stable $resource
 */
class BookingStableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stable = $this->resource;

        return [
            'slug' => (string) $stable->slug,
            'name' => (string) $stable->name,
            /** @var string|null */
            'cover_photo_url' => $stable->cover_photo_url,
            'region' => LookupResource::make($this->whenLoaded('region')),
            'city' => LookupResource::make($this->whenLoaded('city')),
            /** @var string|null */
            'address' => $stable->address,
            /** @var string|null */
            'map_link' => $stable->map_link,
            /** @var string|null */
            'phone' => $stable->phone,
        ];
    }
}
