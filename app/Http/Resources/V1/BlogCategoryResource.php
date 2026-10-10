<?php

namespace App\Http\Resources\V1;

use App\Models\CmsCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CmsCategory
 */
class BlogCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            /** Use it as `?category=` on the posts list. */
            'slug' => (string) $this->slug,
            'name' => (string) $this->getTranslation('name', app()->getLocale()),
            /** Published posts in this category (only on the categories list). */
            'posts_count' => $this->whenCounted('posts', fn () => (int) $this->posts_count),
        ];
    }
}
