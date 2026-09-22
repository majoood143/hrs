<?php

namespace App\Filament\Resources\Api\EventCategory\Handlers;

use App\Filament\Resources\Api\EventCategory\Transformers\EventCategoryTransformer;
use App\Filament\Resources\EventCategoryResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = EventCategoryResource::class;

    protected static string $permission = 'ViewAny:EventCategory';

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

        return EventCategoryTransformer::collection($query);
    }
}
