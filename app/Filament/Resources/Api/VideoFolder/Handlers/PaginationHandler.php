<?php

namespace App\Filament\Resources\Api\VideoFolder\Handlers;

use App\Filament\Resources\Api\VideoFolder\Transformers\VideoFolderTransformer;
use App\Filament\Resources\VideoFolderResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = VideoFolderResource::class;

    protected static string $permission = 'ViewAny:VideoFolder';

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

        return VideoFolderTransformer::collection($query);
    }
}
