<?php

namespace App\Http\Resources\V1;

use App\Models\Video;
use App\Support\Api\RichText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A YouTube video of the library.
 *
 * @mixin Video
 */
class VideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'slug' => (string) $this->slug,
            'title' => (string) $this->getTranslation('title', app()->getLocale()),
            /** Plain text, for cards. */
            'summary' => $this->descriptionText(),
            /**
             * The description as native blocks (heading | subheading | paragraph | list_item | quote, each with `text`).
             *
             * @var list<array{type: string, text: string}>
             */
            'description' => RichText::blocks($this->getTranslation('description', app()->getLocale())),
            /** @var string|null */
            'youtube_id' => $this->youtube_id,
            /** Opens in the YouTube app or the browser. */
            'youtube_url' => $this->youtube_id ? 'https://www.youtube.com/watch?v='.$this->youtube_id : null,
            /** @var string|null */
            'thumbnail_url' => $this->thumbnail_url,
            'folder' => $this->whenLoaded('folder', fn () => [
                'slug' => (string) $this->folder->slug,
                'name' => (string) $this->folder->getTranslation('name', app()->getLocale()),
            ]),
            'views_count' => (int) $this->views_count,
        ];
    }
}
