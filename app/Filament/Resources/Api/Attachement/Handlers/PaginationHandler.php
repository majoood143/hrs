<?php

namespace App\Filament\Resources\Api\Attachement\Handlers;

use App\Filament\Resources\Api\Attachement\Transformers\AttachementTransformer;
use App\Filament\Resources\AttachementResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = AttachementResource::class;

    protected static string $permission = 'ViewAny:Attachement';

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

        return AttachementTransformer::collection($query);
    }
}
