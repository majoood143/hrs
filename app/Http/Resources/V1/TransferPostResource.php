<?php

namespace App\Http\Resources\V1;

use App\Models\TransferPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A transfer-board post: an offer (a transporter with room to spare) or a request (a horse that
 * needs a ride), from one place to another on a date.
 *
 * @mixin TransferPost
 */
class TransferPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            /** `offer` or `request`. */
            'type' => OptionResource::of((string) $this->type, __('transportation.type_'.$this->type)),
            'from' => [
                'country' => LookupResource::make($this->whenLoaded('fromCountry')),
                'region' => LookupResource::make($this->whenLoaded('fromRegion')),
                'city' => LookupResource::make($this->whenLoaded('fromCity')),
            ],
            'to' => [
                'country' => LookupResource::make($this->whenLoaded('toCountry')),
                'region' => LookupResource::make($this->whenLoaded('toRegion')),
                'city' => LookupResource::make($this->whenLoaded('toCity')),
            ],
            /** `Y-m-d`. */
            'transfer_date' => $this->transfer_date?->toDateString(),
            /** Horses: room offered, or horses to move. */
            'capacity' => (int) $this->capacity,
            /** Offers only (requests carry no price). */
            'price' => $this->price === null ? null : new MoneyResource($this->price),
            /** @var string|null */
            'cover_photo_url' => $this->cover_photo_url,
            'contact_number' => (string) $this->contact_number,
            'views_count' => (int) $this->views_count,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
