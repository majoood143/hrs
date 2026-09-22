<?php

namespace App\Filament\Resources\Api\Authority\Handlers;

use App\Filament\Resources\Api\Authority\Transformers\AuthorityTransformer;
use App\Filament\Resources\AuthorityResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = AuthorityResource::class;

    protected static string $permission = 'ViewAny:Authority';

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

        return AuthorityTransformer::collection($query);
    }
}
