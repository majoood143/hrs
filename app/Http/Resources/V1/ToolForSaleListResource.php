<?php

namespace App\Http\Resources\V1;

use App\Models\ToolSalePost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A tool-for-sale card: what the website's listing shows.
 *
 * @mixin ToolSalePost
 */
class ToolForSaleListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'category' => OptionResource::of((string) $this->category, $this->category_label),
            'condition' => OptionResource::of((string) $this->condition, $this->condition_label),
            'brand' => $this->brand,
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
