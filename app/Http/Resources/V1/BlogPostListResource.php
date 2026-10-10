<?php

namespace App\Http\Resources\V1;

use App\Models\CmsPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A blog post card.
 *
 * @mixin CmsPost
 */
class BlogPostListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            /** The detail endpoint's key. */
            'slug' => (string) $this->slug,
            'title' => (string) $this->getTranslation('title', app()->getLocale()),
            /** @var string|null */
            'excerpt' => $this->getTranslation('excerpt', app()->getLocale()) ?: null,
            'image_url' => $this->featuredImageUrl(),
            'category' => BlogCategoryResource::make($this->whenLoaded('category')),
            'published_at' => $this->published_at?->toIso8601String(),
            'views_count' => (int) $this->views_count,
        ];
    }
}
