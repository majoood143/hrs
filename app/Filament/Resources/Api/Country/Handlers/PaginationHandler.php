<?php

namespace App\Filament\Resources\Api\Country\Handlers;

use App\Filament\Resources\Api\Country\Transformers\CountryTransformer;
use App\Filament\Resources\CountryResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CountryResource::class;

    protected static string $permission = 'ViewAny:Country';

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

        return CountryTransformer::collection($query);
    }
}
