<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\PlaceResource;
use App\Models\Center;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CenterController extends PlaceController
{
    protected string $model = Center::class;

    /**
     * List centers
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->list($request);
    }

    /**
     * Show a center
     *
     * @unauthenticated
     */
    public function show(string $slug): PlaceResource
    {
        return $this->find($slug);
    }
}
