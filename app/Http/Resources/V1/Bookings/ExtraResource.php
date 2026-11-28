<?php

namespace App\Http\Resources\V1\Bookings;

use App\Http\Resources\V1\MoneyResource;
use App\Http\Resources\V1\OptionResource;
use App\Models\StableExtra;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An extra the customer may add to this booking (photos, helmet hire…): sent back as
 * `extras[<id>]=<quantity>`, at most `max_quantity` (1 = a tick box).
 *
 * @property-read StableExtra $resource
 */
class ExtraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $extra = $this->resource;

        return [
            'id' => (int) $extra->id,
            'name' => (string) $extra->name,
            /** @var string|null */
            'description' => $extra->description,
            /** @var string|null */
            'photo_url' => $extra->photo_url,
            /** Per unit; per rider when `pricing` is `per_rider`. Before the fee and VAT. */
            'price' => new MoneyResource($extra->price),
            /** `per_booking` | `per_rider` */
            'pricing' => OptionResource::of($extra->pricing, __('stable_extras.'.$extra->pricing)),
            'max_quantity' => (int) $extra->max_quantity,
        ];
    }
}
