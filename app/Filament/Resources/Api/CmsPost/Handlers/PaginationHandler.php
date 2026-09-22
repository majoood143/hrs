<?php

namespace App\Filament\Resources\Api\CmsPost\Handlers;

use App\Filament\Resources\Api\CmsPost\Transformers\CmsPostTransformer;
use App\Filament\Resources\CmsPostResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CmsPostResource::class;

    protected static string $permission = 'ViewAny:CmsPost';

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

        return CmsPostTransformer::collection($query);
    }
}
