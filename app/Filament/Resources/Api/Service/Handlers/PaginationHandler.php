<?php

namespace App\Filament\Resources\Api\Service\Handlers;

use App\Filament\Resources\Api\Service\Transformers\ServiceTransformer;
use App\Filament\Resources\ServiceResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = ServiceResource::class;

    protected static string $permission = 'ViewAny:Service';

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

        return ServiceTransformer::collection($query);
    }
}
