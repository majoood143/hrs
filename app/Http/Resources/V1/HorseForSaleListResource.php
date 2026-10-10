<?php

namespace App\Http\Resources\V1;

use App\Models\HorseSalePost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A horse-for-sale card: what the website's listing shows.
 *
 * @mixin HorseSalePost
 */
class HorseForSaleListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'breed' => $this->breed,
            'type' => LookupResource::make($this->whenLoaded('type')),
            'gender' => LookupResource::make($this->whenLoaded('gender')),
            /** Date of birth, `Y-m-d`. */
            'dob' => $this->dob?->toDateString(),
            /**
             * Whole years old (null without a date of birth); show months instead when 0.
             *
             * @var int|null
             */
            'age_years' => $this->age,
            /** @var int|null */
            'age_months' => $this->age_in_months,
            'country' => LookupResource::make($this->whenLoaded('country')),
            'city' => LookupResource::make($this->whenLoaded('city')),
            'price' => new MoneyResource($this->price),
            'price_negotiable' => (bool) $this->price_negotiable,
            'cover_photo_url' => $this->cover_photo_url,
            'contact_number' => (string) $this->contact_number,
            'views_count' => (int) $this->views_count,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
