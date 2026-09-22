<?php

namespace App\Filament\Resources\Api\Setting\Handlers;

use App\Filament\Resources\Api\Setting\Transformers\SettingTransformer;
use App\Filament\Resources\SettingResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = SettingResource::class;

    protected static string $permission = 'ViewAny:Setting';

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

        return SettingTransformer::collection($query);
    }
}
