<?php

namespace App\Http\Resources\V1\Bookings;

use App\Models\BookingSlot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One bookable time. Dates and times are the stable's wall clock (Muscat); `starts_at` is the same
 * moment with its offset.
 *
 * @property-read BookingSlot $resource
 */
class SlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $slot = $this->resource;

        return [
            'id' => (int) $slot->id,
            /** @example 2026-10-20 */
            'date' => $slot->date->toDateString(),
            /** @example 16:00 */
            'start_time' => $slot->startsAt()->format('H:i'),
            /** @example 17:00 */
            'end_time' => $slot->endsAt()->format('H:i'),
            /** @example 2026-10-20T16:00:00+04:00 */
            'starts_at' => $slot->startsAt()->toIso8601String(),
            'places_left' => $slot->remainingPlaces(),
            'capacity' => (int) $slot->capacity,
            /** @var string|null */
            'trainer' => $slot->relationLoaded('trainer') ? $slot->trainer?->name : null,
        ];
    }
}
