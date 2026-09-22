<?php

namespace App\Filament\Resources\Api\Region\Handlers;

use App\Filament\Resources\Api\Region\Transformers\RegionTransformer;
use App\Filament\Resources\RegionResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = RegionResource::class;

    protected static string $permission = 'ViewAny:Region';

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

        return RegionTransformer::collection($query);
    }
}
