<?php

namespace App\Http\Resources\V1;

use App\Models\HorseSalePost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A horse for sale in full: what the website's detail page shows.
 *
 * @mixin HorseSalePost
 */
class HorseForSaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'description' => $this->description,
            'breed' => $this->breed,
            'type' => LookupResource::make($this->whenLoaded('type')),
            'gender' => LookupResource::make($this->whenLoaded('gender')),
            'color' => ColorResource::make($this->whenLoaded('color')),
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
            'dam' => $this->dam,
            'sire' => $this->sire,
            'birth_country' => LookupResource::make($this->whenLoaded('birthCountry')),
            'passport_number' => $this->passport_number,
            'passport_document_url' => $this->passport_document_url,
            'country' => LookupResource::make($this->whenLoaded('country')),
            'region' => LookupResource::make($this->whenLoaded('region')),
            'city' => LookupResource::make($this->whenLoaded('city')),
            'price' => new MoneyResource($this->price),
            'price_negotiable' => (bool) $this->price_negotiable,
            'cover_photo_url' => $this->cover_photo_url,
            /**
             * The cover photo first, then the gallery.
             *
             * @var list<string>
             */
            'image_urls' => $this->gallery_image_urls,
            'contact_number' => (string) $this->contact_number,
            'views_count' => (int) $this->views_count,
            'created_at' => $this->created_at?->toIso8601String(),
            /** The listing's page on the website, for sharing. */
            'share_url' => route('horses-for-sale.show', $this->resource),
        ];
    }
}
