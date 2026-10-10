<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\PlaceResource;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShopController extends PlaceController
{
    protected string $model = Shop::class;

    /**
     * List shops
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->list($request);
    }

    /**
     * Show a shop
     *
     * @unauthenticated
     */
    public function show(string $slug): PlaceResource
    {
        return $this->find($slug);
    }
}
