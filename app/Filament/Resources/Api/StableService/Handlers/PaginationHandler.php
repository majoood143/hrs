<?php

namespace App\Filament\Resources\Api\StableService\Handlers;

use App\Filament\Resources\Api\StableService\Transformers\StableServiceTransformer;
use App\Filament\Resources\StableServiceResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = StableServiceResource::class;

    protected static string $permission = 'ViewAny:StableService';

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

        return StableServiceTransformer::collection($query);
    }
}
