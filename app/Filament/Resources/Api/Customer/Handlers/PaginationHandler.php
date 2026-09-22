<?php

namespace App\Filament\Resources\Api\Customer\Handlers;

use App\Filament\Resources\Api\Customer\Transformers\CustomerTransformer;
use App\Filament\Resources\CustomerResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CustomerResource::class;

    protected static string $permission = 'ViewAny:Customer';

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

        return CustomerTransformer::collection($query);
    }
}
