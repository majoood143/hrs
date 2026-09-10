<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CmsCategory;
use App\Models\CmsPost;
use Illuminate\View\View;
use Spatie\Tags\Tag;

class PostController extends Controller
{
    public function index(): View
    {
        $posts = CmsPost::query()->published()->latest('published_at')->paginate(9);

        return view('site.post.index', ['posts' => $posts]);
    }

    public function show(string $slug): View
    {
        $post = CmsPost::query()->where('slug', $slug)->published()->firstOrFail();

        return view('site.post.show', ['post' => $post]);
    }

    public function category(string $slug): View
    {
        $category = CmsCategory::query()->where('slug', $slug)->firstOrFail();

        $posts = CmsPost::query()
            ->where('category_id', $category->id)
            ->published()
            ->latest('published_at')
            ->paginate(9);

        return view('site.post.index', [
            'posts' => $posts,
            'heading' => $category->getTranslation('name', app()->getLocale()),
        ]);
    }

    public function tag(string $slug): View
    {
        $tag = Tag::query()
            ->where('slug->' . app()->getLocale(), $slug)
            ->firstOrFail();

        $posts = CmsPost::query()
            ->withAnyTags([$tag])
            ->published()
            ->latest('published_at')
            ->paginate(9);

        return view('site.post.index', [
            'posts' => $posts,
            'heading' => '#' . $tag->getTranslation('name', app()->getLocale()),
        ]);
    }
}
