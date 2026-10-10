<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\PaginatesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BlogCategoryResource;
use App\Http\Resources\V1\BlogPostListResource;
use App\Http\Resources\V1\BlogPostResource;
use App\Models\CmsCategory;
use App\Models\CmsPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The website's blog (`Site\PostController`): published posts only, newest first.
 */
class BlogController extends Controller
{
    use PaginatesListings;

    /**
     * List blog posts
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            /** A category's `slug`. */
            'category' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            /** Defaults to 20, at most 50. */
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $category = filled($filters['category'] ?? null)
            ? CmsCategory::query()->where('slug', $filters['category'])->firstOrFail()
            : null;

        return BlogPostListResource::collection(
            CmsPost::query()->published()
                ->with(['category', 'media'])
                ->when($category, fn ($q) => $q->where('category_id', $category->id))
                ->latest('published_at')
                ->paginate($this->perPage($request))
                ->withQueryString()
        );
    }

    /**
     * Show a blog post
     *
     * @unauthenticated
     */
    public function show(string $slug): BlogPostResource
    {
        return new BlogPostResource(CmsPost::query()->published()->with(['category', 'media'])->where('slug', $slug)->firstOrFail());
    }

    /**
     * List blog categories
     *
     * Only categories with published posts.
     *
     * @unauthenticated
     */
    public function categories(): AnonymousResourceCollection
    {
        return BlogCategoryResource::collection(
            CmsCategory::query()
                ->withCount(['posts' => fn ($q) => $q->published()])
                ->orderBy('order')
                ->get()
                ->filter(fn (CmsCategory $category) => $category->posts_count > 0)
                ->values()
        );
    }
}
