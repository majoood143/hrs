<?php

namespace App\Http\Resources\V1\Owner;

use App\Models\BookingSlot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of the stable's dated times, as the panel's Slots list shows it: how full, and open or
 * closed to new bookings. Dates and times are the stable's wall clock (Muscat).
 *
 * @property-read BookingSlot $resource
 */
class OwnerSlotResource extends JsonResource
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
            'offering' => [
                'id' => (int) $slot->stable_offering_id,
                'name' => (string) $slot->offering?->name,
            ],
            /** @var string|null */
            'trainer' => $slot->trainer?->name,
            'capacity' => (int) $slot->capacity,
            /** Riders in it now (unpaid checkouts included: they hold their places). */
            'booked' => $slot->bookedRiders(),
            'places_left' => $slot->remainingPlaces(),
            /** Closed: no new bookings (the ones made stay). */
            'is_open' => (bool) $slot->is_open,
            'has_started' => $slot->hasStarted(),
            /** Added by hand, not from a weekly schedule. */
            'one_off' => $slot->stable_schedule_id === null,
            /** @var string|null */
            'notes' => $slot->notes,
        ];
    }
}
