<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\MobileApp\AppLinks;
use Illuminate\Http\JsonResponse;

/**
 * The files iPhones and Android phones fetch to let website links open the Muhra app
 * (`Support\MobileApp\AppLinks`). 404 until their keys are set in admin → Mobile app.
 */
class AppLinksController extends Controller
{
    public function apple(): JsonResponse
    {
        return $this->file(AppLinks::appleAssociation());
    }

    public function android(): JsonResponse
    {
        return $this->file(AppLinks::androidAssetLinks());
    }

    private function file(?array $content): JsonResponse
    {
        abort_if($content === null, 404);

        return response()
            ->json($content, 200, [], JSON_UNESCAPED_SLASHES)
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
