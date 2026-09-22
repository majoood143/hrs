<?php

namespace App\Filament\Resources\Api\SuccessStory\Handlers;

use App\Filament\Resources\Api\SuccessStory\Transformers\SuccessStoryTransformer;
use App\Filament\Resources\SuccessStoryResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = SuccessStoryResource::class;

    protected static string $permission = 'ViewAny:SuccessStory';

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

        return SuccessStoryTransformer::collection($query);
    }
}
