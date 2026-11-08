<?php

namespace App\Http\Resources\V1;

use App\Models\Event;
use App\Support\Api\Links;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * A calendar event. Events have no page of their own; `link` (if any) is where to read more.
 *
 * @mixin Event
 */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'title' => (string) $this->title,
            /** @var string|null */
            'description' => $this->description ?: null,
            /** `Y-m-d`. */
            'date' => $this->date?->toDateString(),
            /**
             * `Y-m-d`, the last day of a multi-day event; null for a one-day event.
             *
             * @var string|null
             */
            'end_date' => $this->isMultiDay() ? $this->end_date->toDateString() : null,
            /** `H:i`, on the first day. */
            'start_time' => Carbon::parse($this->start_time)->format('H:i'),
            /** `H:i`, on the last day. */
            'end_time' => Carbon::parse($this->end_time)->format('H:i'),
            'category' => $this->category ? new EventCategoryResource($this->category) : null,
            /** Absolute; a site page or an outside link. */
            'link' => Links::absolute($this->link),
            'is_external_link' => $this->isExternalLink(),
            'views_count' => (int) $this->views_count,
        ];
    }
}
