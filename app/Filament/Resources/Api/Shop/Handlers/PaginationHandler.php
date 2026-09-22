<?php

namespace App\Filament\Resources\Api\Shop\Handlers;

use App\Filament\Resources\Api\Shop\Transformers\ShopTransformer;
use App\Filament\Resources\ShopResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = ShopResource::class;

    protected static string $permission = 'ViewAny:Shop';

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

        return ShopTransformer::collection($query);
    }
}
