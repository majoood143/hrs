<?php

namespace App\Filament\Resources\Api\Color\Handlers;

use App\Filament\Resources\Api\Color\Transformers\ColorTransformer;
use App\Filament\Resources\ColorResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = ColorResource::class;

    protected static string $permission = 'ViewAny:Color';

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

        return ColorTransformer::collection($query);
    }
}
