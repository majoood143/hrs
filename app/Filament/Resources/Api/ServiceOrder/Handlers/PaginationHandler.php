<?php

namespace App\Filament\Resources\Api\ServiceOrder\Handlers;

use App\Filament\Resources\Api\ServiceOrder\Transformers\ServiceOrderTransformer;
use App\Filament\Resources\ServiceOrderResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = ServiceOrderResource::class;

    protected static string $permission = 'ViewAny:ServiceOrder';

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

        return ServiceOrderTransformer::collection($query);
    }
}
