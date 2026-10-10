<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\LookupsResource;

class LookupController extends Controller
{
    /**
     * Filter lookups
     *
     * Countries (with regions and cities), horse types, genders, colours, tool categories and conditions.
     *
     * @unauthenticated
     */
    public function __invoke(): LookupsResource
    {
        return new LookupsResource;
    }
}
