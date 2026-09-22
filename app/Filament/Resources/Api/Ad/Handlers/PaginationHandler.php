<?php

namespace App\Filament\Resources\Api\Ad\Handlers;

use App\Filament\Resources\AdResource;
use App\Filament\Resources\Api\Ad\Transformers\AdTransformer;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = AdResource::class;

    protected static string $permission = 'ViewAny:Ad';

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

        return AdTransformer::collection($query);
    }
}
