<?php

namespace App\Filament\Resources\Api\CmsMenu\Handlers;

use App\Filament\Resources\Api\CmsMenu\Transformers\CmsMenuTransformer;
use App\Filament\Resources\CmsMenuResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CmsMenuResource::class;

    protected static string $permission = 'ViewAny:CmsMenu';

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

        return CmsMenuTransformer::collection($query);
    }
}
