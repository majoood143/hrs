<?php

namespace App\Filament\Resources\Api\CmsPage\Handlers;

use App\Filament\Resources\Api\CmsPage\Transformers\CmsPageTransformer;
use App\Filament\Resources\CmsPageResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CmsPageResource::class;

    protected static string $permission = 'ViewAny:CmsPage';

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

        return CmsPageTransformer::collection($query);
    }
}
