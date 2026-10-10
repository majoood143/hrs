<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CreatesPublicPosts;
use App\Http\Controllers\Api\V1\Concerns\PaginatesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ToolForSaleListResource;
use App\Http\Resources\V1\ToolForSaleResource;
use App\Models\ToolSalePost;
use App\Services\PublicPosts\ToolSalePostCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * The website's tools-for-sale board (`Site\ToolSaleController` and its listing): active posts only.
 */
class ToolForSaleController extends Controller
{
    use CreatesPublicPosts;
    use PaginatesListings;

    /**
     * List tools for sale
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::in(ToolSalePost::CATEGORIES)],
            'condition' => ['nullable', Rule::in(ToolSalePost::CONDITIONS)],
            'country_id' => ['nullable', 'integer'],
            'region_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            /** Newest first by default. */
            'sort' => ['nullable', Rule::in(ToolSalePost::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
            /** Defaults to 20, at most 50. */
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $tools = ToolSalePost::query()
            ->visible()
            ->with(['city', 'country'])
            ->filteredBy($filters)
            ->sortedBy($filters['sort'] ?? null)
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ToolForSaleListResource::collection($tools);
    }

    /**
     * Show a tool for sale
     *
     * @unauthenticated
     */
    public function show(int $id): ToolForSaleResource
    {
        $tool = ToolSalePost::visible()
            ->with(['country', 'region', 'city'])
            ->findOrFail($id);

        return new ToolForSaleResource($tool);
    }

    /**
     * Post a tool for sale
     *
     * Needs `posting_token` (from verification/verify); the post's contact number is the verified
     * phone. Published at once, like the website. `cover_photo` (required) is a file; send multipart/form-data. 401 without a valid token, 429 past the
     * daily limit (5 posts per phone).
     *
     * @unauthenticated
     */
    public function store(Request $request, ToolSalePostCreator $creator): JsonResponse
    {
        $post = $this->createPublicPost($request, $creator, ['cover_photo']);

        return (new ToolForSaleResource($post->load(['country', 'region', 'city'])))->response()->setStatusCode(201);
    }
}
