<?php

namespace App\Filament\Resources\Api\ClinicService\Handlers;

use App\Filament\Resources\Api\ClinicService\Transformers\ClinicServiceTransformer;
use App\Filament\Resources\ClinicServiceResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = ClinicServiceResource::class;

    protected static string $permission = 'ViewAny:ClinicService';

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

        return ClinicServiceTransformer::collection($query);
    }
}
