<?php

namespace App\Filament\Resources\Api\MediaLibrary\Handlers;

use App\Filament\Resources\Api\MediaLibrary\Transformers\MediaLibraryTransformer;
use App\Filament\Resources\MediaLibraryResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = MediaLibraryResource::class;

    protected static string $permission = 'ViewAny:MediaLibrary';

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

        return MediaLibraryTransformer::collection($query);
    }
}
