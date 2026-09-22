<?php

namespace App\Filament\Resources\Api\NotificationLog\Handlers;

use App\Filament\Resources\Api\NotificationLog\Transformers\NotificationLogTransformer;
use App\Filament\Resources\NotificationLogResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = NotificationLogResource::class;

    protected static string $permission = 'ViewAny:NotificationLog';

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

        return NotificationLogTransformer::collection($query);
    }
}
