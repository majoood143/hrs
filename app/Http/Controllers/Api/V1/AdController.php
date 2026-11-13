<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AdResource;
use App\Support\MobileApp\AppClient;
use App\Support\MobileApp\MobileAds;
use App\Support\MobileApp\MobileFeatures;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdController extends Controller
{
    /**
     * Ads of a placement
     *
     * The ads of the zone picked for this spot in the app (admin → Mobile app → Ad zones): `horses`,
     * `tools` or `transfers` (the top of each board). Empty when no zone is picked or the `promos`
     * feature is off for this app. Home's come with `GET home`.
     *
     * @unauthenticated
     */
    public function __invoke(Request $request, string $placement): AnonymousResourceCollection
    {
        $zone = MobileAds::zoneFor($placement);
        $on = MobileFeatures::enabledFor('promos', AppClient::fromRequest($request));

        return AdResource::collection($on && $zone !== null ? MobileAds::ads($zone) : collect());
    }
}
