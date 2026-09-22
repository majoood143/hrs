<?php

namespace App\Filament\Resources\Api\Stable\Handlers;

use App\Filament\Resources\Api\Stable\Transformers\StableTransformer;
use App\Filament\Resources\StableResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = StableResource::class;

    protected static string $permission = 'ViewAny:Stable';

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

        return StableTransformer::collection($query);
    }
}
