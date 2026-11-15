<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PageResource;
use App\Support\MobileApp\MobilePages;

/** Website pages switched on for the app (admin → Mobile app → Pages); `app-status.pages` lists them. */
class PageController extends Controller
{
    /**
     * Page
     *
     * Only a page listed in admin → Mobile app → Pages and published; else 404.
     *
     * @unauthenticated
     */
    public function show(string $slug): PageResource
    {
        $page = MobilePages::find($slug);

        abort_unless($page, 404);

        return PageResource::make($page);
    }
}
