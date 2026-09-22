<?php

namespace App\Filament\Resources\Api\Pollination\Handlers;

use App\Filament\Resources\Api\Pollination\Transformers\PollinationTransformer;
use App\Filament\Resources\PollinationResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = PollinationResource::class;

    protected static string $permission = 'ViewAny:Pollination';

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

        return PollinationTransformer::collection($query);
    }
}
