<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CreatesPublicPosts;
use App\Http\Controllers\Api\V1\Concerns\PaginatesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\JockeyResource;
use App\Models\Jockey;
use App\Services\PublicPosts\JockeyCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * The jockeys directory (app only for now, no website page): active jockeys only.
 */
class JockeyController extends Controller
{
    use CreatesPublicPosts;
    use PaginatesListings;

    /**
     * List jockeys
     *
     * Newest first.
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            /** A riding type they ride. */
            'type' => ['nullable', Rule::in(Jockey::TYPES)],
            'country_id' => ['nullable', 'integer'],
            'region_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            /** Defaults to 20, at most 50. */
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        return JockeyResource::collection(
            Jockey::query()->visible()->with(['country', 'region', 'city'])
                ->filteredBy($filters)
                ->latest('id')
                ->paginate($this->perPage($request))
                ->withQueryString()
        );
    }

    /**
     * Show a jockey
     *
     * @unauthenticated
     */
    public function show(int $id): JockeyResource
    {
        return new JockeyResource(Jockey::visible()->with(['country', 'region', 'city'])->findOrFail($id));
    }

    /**
     * Add a jockey
     *
     * Needs `posting_token` (from verification/verify); the contact number is the verified phone.
     * `types[]` is one or more of the riding types; `cover_photo` (optional) is a file, so send
     * multipart/form-data. `racing_jockey_id` (optional) links their racing club profile, shown only
     * after an admin checked it. Kept for an admin's approval while app posts are reviewed
     * (`meta.pending_review`). 401 without a valid token, 429 past the daily limit.
     *
     * @unauthenticated
     */
    public function store(Request $request, JockeyCreator $creator): JsonResponse
    {
        $post = $this->createPublicPost($request, $creator, ['cover_photo']);

        return $this->postedResponse(new JockeyResource($post->load(['country', 'region', 'city'])), $post);
    }
}
