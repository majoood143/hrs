<?php

namespace App\Filament\Resources\Api\AdZone\Handlers;

use App\Filament\Resources\AdZoneResource;
use App\Filament\Resources\Api\AdZone\Transformers\AdZoneTransformer;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = AdZoneResource::class;

    protected static string $permission = 'ViewAny:AdZone';

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

        return AdZoneTransformer::collection($query);
    }
}
