<?php

namespace App\Filament\Resources\Api\Center\Handlers;

use App\Filament\Resources\Api\Center\Transformers\CenterTransformer;
use App\Filament\Resources\CenterResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CenterResource::class;

    protected static string $permission = 'ViewAny:Center';

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

        return CenterTransformer::collection($query);
    }
}
