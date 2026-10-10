<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\PlaceResource;
use App\Models\Clinic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClinicController extends PlaceController
{
    protected string $model = Clinic::class;

    /**
     * List clinics
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->list($request);
    }

    /**
     * Show a clinic
     *
     * @unauthenticated
     */
    public function show(string $slug): PlaceResource
    {
        return $this->find($slug);
    }
}
