<?php

namespace App\Filament\Resources\Api\CmsCategory\Handlers;

use App\Filament\Resources\Api\CmsCategory\Transformers\CmsCategoryTransformer;
use App\Filament\Resources\CmsCategoryResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CmsCategoryResource::class;

    protected static string $permission = 'ViewAny:CmsCategory';

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

        return CmsCategoryTransformer::collection($query);
    }
}
