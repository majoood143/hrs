<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CreatesPublicPosts;
use App\Http\Controllers\Api\V1\Concerns\PaginatesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\HorseForSaleListResource;
use App\Http\Resources\V1\HorseForSaleResource;
use App\Models\HorseSalePost;
use App\Services\PublicPosts\HorseSalePostCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * The website's horses-for-sale board (`Site\HorseForSaleController` and its listing): active posts only.
 */
class HorseForSaleController extends Controller
{
    use CreatesPublicPosts;
    use PaginatesListings;

    /**
     * List horses for sale
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'type_id' => ['nullable', 'integer'],
            'gender_id' => ['nullable', 'integer'],
            'country_id' => ['nullable', 'integer'],
            'region_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            /** Part of the breed's name. */
            'breed' => ['nullable', 'string', 'max:255'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            /** Newest first by default. */
            'sort' => ['nullable', Rule::in(HorseSalePost::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
            /** Defaults to 20, at most 50. */
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $posts = HorseSalePost::query()
            ->visible()
            ->with(['type', 'gender', 'city', 'country'])
            ->filteredBy($filters)
            ->sortedBy($filters['sort'] ?? null)
            ->paginate($this->perPage($request))
            ->withQueryString();

        return HorseForSaleListResource::collection($posts);
    }

    /**
     * Show a horse for sale
     *
     * @unauthenticated
     */
    public function show(int $id): HorseForSaleResource
    {
        $post = HorseSalePost::visible()
            ->with(['type', 'gender', 'color', 'country', 'region', 'city', 'birthCountry'])
            ->findOrFail($id);

        return new HorseForSaleResource($post);
    }

    /**
     * Post a horse for sale
     *
     * Needs `posting_token` (from verification/verify); the post's contact number is the verified
     * phone. Published at once like the website, or kept for an admin's approval when app posts are
     * reviewed (`meta.pending_review`: the post is not visible yet, so do not open it). `cover_photo` (required), `images[]` (up to 10) and `passport_document` (image or PDF) are files; send multipart/form-data. 401 without a valid token, 429 past the
     * daily limit (posts per phone in 24 h; 5 unless changed in admin → Mobile app → Posting).
     *
     * @unauthenticated
     */
    public function store(Request $request, HorseSalePostCreator $creator): JsonResponse
    {
        $post = $this->createPublicPost($request, $creator, ['cover_photo', 'images', 'passport_document']);

        return $this->postedResponse(new HorseForSaleResource($post->load(['type', 'gender', 'color', 'country', 'region', 'city', 'birthCountry'])), $post);
    }
}
