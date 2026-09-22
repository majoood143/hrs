<?php

namespace App\Filament\Resources\Api\Partner\Handlers;

use App\Filament\Resources\Api\Partner\Transformers\PartnerTransformer;
use App\Filament\Resources\PartnerResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = PartnerResource::class;

    protected static string $permission = 'ViewAny:Partner';

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

        return PartnerTransformer::collection($query);
    }
}
