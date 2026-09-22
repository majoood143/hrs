<?php

namespace App\Filament\Resources\Api\Horse\Handlers;

use App\Filament\Resources\Api\Horse\Transformers\HorseTransformer;
use App\Filament\Resources\HorseResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = HorseResource::class;

    protected static string $permission = 'ViewAny:Horse';

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

        return HorseTransformer::collection($query);
    }
}
