<?php

namespace App\Filament\Resources\Api\Type\Handlers;

use App\Filament\Resources\Api\Type\Transformers\TypeTransformer;
use App\Filament\Resources\TypeResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = TypeResource::class;

    protected static string $permission = 'ViewAny:Type';

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

        return TypeTransformer::collection($query);
    }
}
