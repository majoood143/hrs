<?php

namespace App\Filament\Resources\Api\Video\Handlers;

use App\Filament\Resources\Api\Video\Transformers\VideoTransformer;
use App\Filament\Resources\VideoResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = VideoResource::class;

    protected static string $permission = 'ViewAny:Video';

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

        return VideoTransformer::collection($query);
    }
}
