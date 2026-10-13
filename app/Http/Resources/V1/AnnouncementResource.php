<?php

namespace App\Http\Resources\V1;

use App\Models\MobileAnnouncement;
use App\Support\Api\Links;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A popup for the app, in the request's language. The app shows at most one per launch (highest
 * `priority` first) and remembers by `id` what it showed, following `frequency`.
 *
 * @mixin MobileAnnouncement
 */
class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $button = null;

        if ($this->action_type === MobileAnnouncement::ACTION_URL && ($url = Links::absolute($this->action_url))) {
            $button = ['label' => $this->buttonLabel(), 'url' => $url, 'screen' => null];
        } elseif ($this->action_type === MobileAnnouncement::ACTION_SCREEN && filled($this->action_screen)) {
            $button = ['label' => $this->buttonLabel(), 'url' => null, 'screen' => (string) $this->action_screen];
        }

        return [
            'id' => $this->id,
            'title' => (string) $this->localized('title'),
            /** @var string|null */
            'body' => $this->localized('body'),
            /** @var string|null */
            'image_url' => $this->imageUrl(),
            /**
             * A link (opened in the in-app browser) or an app screen key (`horses_for_sale`, `post_horse`…).
             *
             * @var array{label: string, url: string|null, screen: string|null}|null
             */
            'button' => $button,
            /** @var 'once'|'daily'|'every_launch' */
            'frequency' => (string) $this->frequency,
            /** False: no close button, only the button closes it (it always has one then). */
            'dismissible' => (bool) $this->dismissible || $button === null,
            'priority' => (int) $this->priority,
            /** Changes when the admin edits it. */
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function buttonLabel(): string
    {
        return $this->localized('button_label') ?? __('api.app.announcement_button');
    }
}
