<?php

namespace App\Filament\Resources\Api\CommissionSetting\Handlers;

use App\Filament\Resources\Api\CommissionSetting\Transformers\CommissionSettingTransformer;
use App\Filament\Resources\CommissionSettingResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CommissionSettingResource::class;

    protected static string $permission = 'ViewAny:CommissionSetting';

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

        return CommissionSettingTransformer::collection($query);
    }
}
