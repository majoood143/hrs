<?php

namespace App\Filament\Resources\Api\ToolSalePost\Handlers;

use App\Filament\Resources\Api\ToolSalePost\Transformers\ToolSalePostTransformer;
use App\Filament\Resources\ToolSalePostResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = ToolSalePostResource::class;

    protected static string $permission = 'ViewAny:ToolSalePost';

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

        return ToolSalePostTransformer::collection($query);
    }
}
