<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CreatesPublicPosts;
use App\Http\Controllers\Api\V1\Concerns\PaginatesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TransferPostResource;
use App\Models\TransferPost;
use App\Services\PublicPosts\TransferPostCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * The website's transfer board (`Site\TransferBoardController` and its listing): active posts
 * whose date has not passed.
 */
class TransferBoardController extends Controller
{
    use CreatesPublicPosts;
    use PaginatesListings;

    private const RELATIONS = ['fromCountry', 'fromRegion', 'fromCity', 'toCountry', 'toRegion', 'toCity'];

    /**
     * List transfer posts
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(TransferPost::TYPES)],
            'from_country_id' => ['nullable', 'integer'],
            'from_city_id' => ['nullable', 'integer'],
            'to_country_id' => ['nullable', 'integer'],
            'to_city_id' => ['nullable', 'integer'],
            /** `Y-m-d`. */
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'capacity_min' => ['nullable', 'integer', 'min:1'],
            /** Ignored for requests (they carry no price). */
            'price_max' => ['nullable', 'numeric', 'min:0'],
            /** Soonest transfer first by default. */
            'sort' => ['nullable', Rule::in(TransferPost::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
            /** Defaults to 20, at most 50. */
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        return TransferPostResource::collection(
            TransferPost::query()->visible()->with(self::RELATIONS)
                ->filteredBy($filters)
                ->sortedBy($filters['sort'] ?? null)
                ->paginate($this->perPage($request))
                ->withQueryString()
        );
    }

    /**
     * Show a transfer post
     *
     * @unauthenticated
     */
    public function show(int $id): TransferPostResource
    {
        return new TransferPostResource(TransferPost::visible()->with(self::RELATIONS)->findOrFail($id));
    }

    /**
     * Post a transfer post
     *
     * Needs `posting_token` (from verification/verify); the post's contact number is the verified
     * phone. Published at once, like the website. `cover_photo` (optional) is a file; send multipart/form-data. `price` is required for offers. 401 without a valid token, 429 past the
     * daily limit (5 posts per phone).
     *
     * @unauthenticated
     */
    public function store(Request $request, TransferPostCreator $creator): JsonResponse
    {
        $post = $this->createPublicPost($request, $creator, ['cover_photo']);

        return (new TransferPostResource($post->load(self::RELATIONS)))->response()->setStatusCode(201);
    }
}
