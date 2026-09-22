<?php

namespace App\Filament\Resources\Api\ServiceFeeSetting\Handlers;

use App\Filament\Resources\Api\ServiceFeeSetting\Transformers\ServiceFeeSettingTransformer;
use App\Filament\Resources\ServiceFeeSettingResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = ServiceFeeSettingResource::class;

    protected static string $permission = 'ViewAny:ServiceFeeSetting';

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

        return ServiceFeeSettingTransformer::collection($query);
    }
}
