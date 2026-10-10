<?php

namespace App\Http\Resources\V1;

use App\Models\CmsPost;
use App\Support\Api\PostBlocks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A blog post with its content as native blocks.
 *
 * @mixin CmsPost
 */
class BlogPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'slug' => (string) $this->slug,
            'title' => (string) $this->getTranslation('title', app()->getLocale()),
            /** @var string|null */
            'excerpt' => $this->getTranslation('excerpt', app()->getLocale()) ?: null,
            'image_url' => $this->featuredImageUrl(),
            'category' => BlogCategoryResource::make($this->whenLoaded('category')),
            'published_at' => $this->published_at?->toIso8601String(),
            'views_count' => (int) $this->views_count,
            /**
             * In order. `type` is heading | subheading | paragraph | list_item | quote (with `text`),
             * image (`url`, `alt`, `caption`), video (`youtube_id`, `url`, `thumbnail_url`, `caption`),
             * divider, cta (`heading`, `text`, `button_label`, `button_url`), or unsupported (a part
             * only the website can show: offer `website_url`).
             *
             * @var list<array<string, mixed>>
             */
            'blocks' => PostBlocks::from($this->content),
            /** The post on the website, in this language. */
            'website_url' => route('blog.show', $this->slug).'?lang='.app()->getLocale(),
        ];
    }
}
