<?php

namespace App\Filament\Resources\Api\ShopService\Handlers;

use App\Filament\Resources\Api\ShopService\Transformers\ShopServiceTransformer;
use App\Filament\Resources\ShopServiceResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = ShopServiceResource::class;

    protected static string $permission = 'ViewAny:ShopService';

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

        return ShopServiceTransformer::collection($query);
    }
}
