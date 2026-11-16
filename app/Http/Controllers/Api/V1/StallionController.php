<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\PaginatesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\StallionResource;
use App\Models\Stallion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Breeding stallions in Oman (added by an admin): active ones only. */
class StallionController extends Controller
{
    use PaginatesListings;

    /**
     * List stallions
     *
     * Alphabetical in the request's language.
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            /** Part of the name, the sire's or the dam's (English or Arabic). */
            'q' => ['nullable', 'string', 'max:100'],
            'colour' => ['nullable', Rule::in(Stallion::COLOURS)],
            'page' => ['nullable', 'integer', 'min:1'],
            /** Defaults to 20, at most 50. */
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        return StallionResource::collection(
            Stallion::query()->visible()->with('city')
                ->filteredBy($filters)
                ->byName()
                ->paginate($this->perPage($request))
                ->withQueryString()
        );
    }

    /**
     * Show a stallion
     *
     * @unauthenticated
     */
    public function show(int $id): StallionResource
    {
        return new StallionResource(Stallion::visible()->with('city')->findOrFail($id));
    }
}
