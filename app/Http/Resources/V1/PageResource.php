<?php

namespace App\Http\Resources\V1;

use App\Models\CmsPage;
use App\Support\Api\PostBlocks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A website page the app shows (admin → Mobile app → Pages): native blocks when the app can draw
 * all of it, else the website page for a WebView.
 *
 * @mixin CmsPage
 */
class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $blocks = PostBlocks::from($this->content);
        $locale = app()->getLocale();

        return [
            'slug' => (string) $this->slug,
            'title' => (string) $this->getTranslation('title', $locale),
            /** @var string|null */
            'excerpt' => $this->getTranslation('excerpt', $locale) ?: null,
            /**
             * Same shapes as a blog post's blocks (`unsupported` = a part only the website can show).
             *
             * @var list<array<string, mixed>>
             */
            'blocks' => $blocks,
            /** True when every block is drawn natively and the page runs no scripts of its own; else open `web_url`. */
            'native' => ! in_array('unsupported', array_column($blocks, 'type'), true)
                && blank($this->custom_head_scripts) && blank($this->custom_body_scripts),
            /** True when the page has a part for signed-in customers (a game): the app signs its WebView in first (`POST account/web-session`). */
            'sign_in' => in_array('game_campaign', array_column((array) $this->content, 'type'), true),
            /** The page for the app's WebView (no website header or footer), in this language. */
            'web_url' => route('page.show', $this->slug).'?app=1&lang='.$locale,
            /** The page on the website without a language, for sharing. */
            'share_url' => route('page.show', $this->slug),
        ];
    }
}
