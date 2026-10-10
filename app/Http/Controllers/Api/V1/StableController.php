<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\PlaceResource;
use App\Models\Stable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StableController extends PlaceController
{
    protected string $model = Stable::class;

    /**
     * List stables
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->list($request);
    }

    /**
     * Show a stable
     *
     * @unauthenticated
     */
    public function show(string $slug): PlaceResource
    {
        return $this->find($slug);
    }
}
