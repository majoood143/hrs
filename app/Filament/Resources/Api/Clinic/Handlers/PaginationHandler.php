<?php

namespace App\Filament\Resources\Api\Clinic\Handlers;

use App\Filament\Resources\Api\Clinic\Transformers\ClinicTransformer;
use App\Filament\Resources\ClinicResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = ClinicResource::class;

    protected static string $permission = 'ViewAny:Clinic';

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

        return ClinicTransformer::collection($query);
    }
}
