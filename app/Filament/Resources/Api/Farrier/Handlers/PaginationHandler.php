<?php

namespace App\Filament\Resources\Api\Farrier\Handlers;

use App\Filament\Resources\Api\Farrier\Transformers\FarrierTransformer;
use App\Filament\Resources\FarrierResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = FarrierResource::class;

    protected static string $permission = 'ViewAny:Farrier';

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

        return FarrierTransformer::collection($query);
    }
}
