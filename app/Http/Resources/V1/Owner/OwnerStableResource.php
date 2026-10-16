<?php

namespace App\Http\Resources\V1\Owner;

use App\Http\Resources\V1\OptionResource;
use App\Models\Stable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of the owner's stables: where it stands with the admins and whether customers can book it.
 *
 * @property-read Stable $resource
 */
class OwnerStableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stable = $this->resource;
        $approval = $stable->approval_status;

        return [
            'id' => (int) $stable->id,
            'slug' => (string) $stable->slug,
            'name' => (string) $stable->name,
            /** @var string|null */
            'cover_photo_url' => $stable->cover_photo_url,
            /** pending | approved | rejected | suspended */
            'approval' => OptionResource::of($approval->value, $approval->label()),
            /** success | warning | danger */
            'approval_tone' => $approval->color(),
            /** Shown on the site, approved and with a commission agreed: customers can book it. */
            'accepts_bookings' => $stable->acceptsBookings(),
            /** The owner's place in it: `owner` or `staff`. @var string|null */
            'role' => $stable->pivot?->role,
            /** The website's /stable panel for it (services, schedules, prices, statements…). */
            'panel_url' => url('/stable/'.$stable->slug),
            /** Its public page, once it is shown on the site. @var string|null */
            'page_url' => $stable->is_active && $stable->isApproved() ? route('stables.show', $stable->slug) : null,
        ];
    }
}
