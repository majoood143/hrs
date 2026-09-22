<?php

namespace App\Filament\Resources\Api\CenterService\Handlers;

use App\Filament\Resources\Api\CenterService\Transformers\CenterServiceTransformer;
use App\Filament\Resources\CenterServiceResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CenterServiceResource::class;

    protected static string $permission = 'ViewAny:CenterService';

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

        return CenterServiceTransformer::collection($query);
    }
}
