<?php

namespace App\Filament\Resources\Api\CmsTag\Handlers;

use App\Filament\Resources\Api\CmsTag\Transformers\CmsTagTransformer;
use App\Filament\Resources\CmsTagResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CmsTagResource::class;

    protected static string $permission = 'ViewAny:CmsTag';

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

        return CmsTagTransformer::collection($query);
    }
}
