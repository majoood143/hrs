<?php

namespace App\Http\Resources\V1;

use App\Models\Farrier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A farrier card: what the website's listing shows.
 *
 * @mixin Farrier
 */
class FarrierListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'specialty' => $this->specialty,
            'years_experience' => $this->years_experience,
            'country' => LookupResource::make($this->whenLoaded('country')),
            'city' => LookupResource::make($this->whenLoaded('city')),
            /** Their starting price, when they give one. */
            'price' => $this->price === null ? null : new MoneyResource($this->price),
            'cover_photo_url' => $this->cover_photo_url,
            'contact_number' => (string) $this->contact_number,
            'views_count' => (int) $this->views_count,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
