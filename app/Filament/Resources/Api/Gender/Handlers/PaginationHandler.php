<?php

namespace App\Filament\Resources\Api\Gender\Handlers;

use App\Filament\Resources\Api\Gender\Transformers\GenderTransformer;
use App\Filament\Resources\GenderResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = GenderResource::class;

    protected static string $permission = 'ViewAny:Gender';

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

        return GenderTransformer::collection($query);
    }
}
