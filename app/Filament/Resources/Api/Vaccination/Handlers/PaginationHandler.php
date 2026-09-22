<?php

namespace App\Filament\Resources\Api\Vaccination\Handlers;

use App\Filament\Resources\Api\Vaccination\Transformers\VaccinationTransformer;
use App\Filament\Resources\VaccinationResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = VaccinationResource::class;

    protected static string $permission = 'ViewAny:Vaccination';

    public function handler()
    {
        $query = static::getEloquentQuery();

        $query = QueryBuilder::for($query)
            ->allowedFields($this->getAllowedFields() ?? [])
            ->allowedSorts($this->getAllowedSorts() ?? [])
            ->allowedFilters($this->getAllowedFilters() ?? [])
            ->allowedIncludes($this->getAllowedIncludes() ?? [])
            ->paginate(request()->query('per_page'))
            ->appends(request()->query());

        return VaccinationTransformer::collection($query);
    }
}
