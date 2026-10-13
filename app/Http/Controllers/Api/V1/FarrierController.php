<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CreatesPublicPosts;
use App\Http\Controllers\Api\V1\Concerns\PaginatesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\FarrierListResource;
use App\Http\Resources\V1\FarrierResource;
use App\Models\Farrier;
use App\Services\PublicPosts\FarrierCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * The website's farriers board (`Site\FarrierController` and its listing): active farriers only.
 */
class FarrierController extends Controller
{
    use CreatesPublicPosts;
    use PaginatesListings;

    /**
     * List farriers
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            /** Part of the specialty. */
            'specialty' => ['nullable', 'string', 'max:255'],
            'country_id' => ['nullable', 'integer'],
            'region_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            /** Newest first by default. */
            'sort' => ['nullable', Rule::in(Farrier::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
            /** Defaults to 20, at most 50. */
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        return FarrierListResource::collection(
            Farrier::query()->visible()->with(['city', 'country'])
                ->filteredBy($filters)
                ->sortedBy($filters['sort'] ?? null)
                ->paginate($this->perPage($request))
                ->withQueryString()
        );
    }

    /**
     * Show a farrier
     *
     * @unauthenticated
     */
    public function show(int $id): FarrierResource
    {
        return new FarrierResource(Farrier::visible()->with(['country', 'region', 'city'])->findOrFail($id));
    }

    /**
     * Post a farrier
     *
     * Needs `posting_token` (from verification/verify); the post's contact number is the verified
     * phone. Published at once like the website, or kept for an admin's approval when app posts are
     * reviewed (`meta.pending_review`: the post is not visible yet, so do not open it). `cover_photo` (required) is a file; send multipart/form-data. 401 without a valid token, 429 past the
     * daily limit (posts per phone in 24 h; 5 unless changed in admin → Mobile app → Posting).
     *
     * @unauthenticated
     */
    public function store(Request $request, FarrierCreator $creator): JsonResponse
    {
        $post = $this->createPublicPost($request, $creator, ['cover_photo']);

        return $this->postedResponse(new FarrierResource($post->load(['country', 'region', 'city'])), $post);
    }
}
