<?php

namespace App\Http\Resources\V1;

use App\Models\VideoFolder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A video library folder.
 *
 * @mixin VideoFolder
 */
class VideoFolderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            /** The folder endpoint's key. */
            'slug' => (string) $this->slug,
            'name' => (string) $this->getTranslation('name', app()->getLocale()),
            /** The folder's card image, else its first video's thumbnail. */
            'image_url' => $this->cardImageUrl() ?? $this->videos()->active()->orderBy('order')->orderBy('id')->first()?->thumbnail_url,
            /** Active videos here and in every sub-folder. */
            'videos_count' => $this->activeVideosCount(),
        ];
    }
}
