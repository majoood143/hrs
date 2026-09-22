<?php

namespace App\Filament\Resources\Api\HorseSalePost\Handlers;

use App\Filament\Resources\Api\HorseSalePost\Transformers\HorseSalePostTransformer;
use App\Filament\Resources\HorseSalePostResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = HorseSalePostResource::class;

    protected static string $permission = 'ViewAny:HorseSalePost';

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

        return HorseSalePostTransformer::collection($query);
    }
}
