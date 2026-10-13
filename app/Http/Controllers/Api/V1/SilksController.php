<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SilksCatalogResource;
use App\Support\Silks\SilksCatalog;

class SilksController extends Controller
{
    /**
     * Silks designer catalog
     *
     * The racing silks designer's colours and each area's patterns (admin → CMS → silk colours and
     * patterns), the default design, the description templates and the website page that opens a
     * design. The app draws and describes the design itself, like the website's script.
     *
     * @unauthenticated
     */
    public function __invoke(): SilksCatalogResource
    {
        return new SilksCatalogResource(SilksCatalog::load());
    }
}
