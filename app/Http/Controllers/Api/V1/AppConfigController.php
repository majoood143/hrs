<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AppConfigResource;

class AppConfigController extends Controller
{
    /**
     * App configuration
     *
     * Site name, logos, theme colours and fonts, currency, contact details, social links and terms,
     * from General Settings. Cache it on the device and refresh it on launch.
     *
     * @unauthenticated
     */
    public function __invoke(): AppConfigResource
    {
        return new AppConfigResource;
    }
}
