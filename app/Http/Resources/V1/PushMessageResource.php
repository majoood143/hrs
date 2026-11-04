<?php

namespace App\Http\Resources\V1;

use App\Models\MobileAnnouncement;
use App\Models\PushDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A notification in the app's list, in the request's language.
 *
 * @mixin PushDelivery
 */
class PushMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $campaign = $this->campaign;

        return [
            /** The campaign's id (the one a tap reports as opened). */
            'id' => $campaign->id,
            'title' => (string) $campaign->localized('title'),
            /** @var string|null */
            'body' => $campaign->localized('body'),
            /** @var string|null */
            'image_url' => $campaign->imageUrl(),
            /**
             * What a tap opens: an app screen key (`horses_for_sale`, `post_horse`…) or a link; both null = nothing.
             *
             * @var string|null
             */
            'screen' => $campaign->action_type === MobileAnnouncement::ACTION_SCREEN ? $campaign->action_screen : null,
            /** @var string|null */
            'url' => $campaign->action_type === MobileAnnouncement::ACTION_URL ? $campaign->action_url : null,
            'sent_at' => $this->created_at?->toIso8601String(),
            'opened' => $this->opened_at !== null,
        ];
    }
}
