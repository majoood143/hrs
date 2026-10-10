<?php

namespace App\Http\Resources\V1;

use App\Models\Event;
use App\Models\EventCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventCategory
 */
class EventCategoryResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, color: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            /** Hex colour of the calendar's dot and badge. */
            'color' => (string) ($this->color ?: Event::DEFAULT_CATEGORY_COLOR),
        ];
    }
}
