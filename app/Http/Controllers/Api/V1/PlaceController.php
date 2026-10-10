<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\PaginatesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PlaceListResource;
use App\Http\Resources\V1\PlaceResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * The directory places (stables, clinics, centers, shops): the website's boards, `active()` only
 * (stables: approved). One subclass per kind, so each gets its own documented endpoints.
 */
abstract class PlaceController extends Controller
{
    use PaginatesListings;

    /** @var class-string<Model> */
    protected string $model;

    protected function filters(Request $request): array
    {
        $model = $this->model;

        return $request->validate([
            /** Clinics, centers and shops: one of the `*_types` lookups. */
            'type' => ['nullable', Rule::in(defined($model.'::TYPES') ? array_keys($model::TYPES) : [])],
            /** One of this kind's `*_services` lookups. */
            'service_id' => ['nullable', 'integer'],
            'country_id' => ['nullable', 'integer'],
            'region_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            /** By name by default. */
            'sort' => ['nullable', Rule::in($model::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
            /** Defaults to 20, at most 50. */
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    protected function list(Request $request): AnonymousResourceCollection
    {
        $filters = $this->filters($request);

        return PlaceListResource::collection(
            $this->model::query()->active()->with(['city', 'country', 'services'])
                ->filteredBy($filters)
                ->sortedBy($filters['sort'] ?? null)
                ->paginate($this->perPage($request))
                ->withQueryString()
        );
    }

    protected function find(string $slug): PlaceResource
    {
        return new PlaceResource(
            $this->model::query()->active()->with(['country', 'region', 'city', 'services'])->where('slug', $slug)->firstOrFail()
        );
    }
}
