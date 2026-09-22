<?php

namespace App\Filament\Resources\Api\TransferPost\Handlers;

use App\Filament\Resources\Api\TransferPost\Transformers\TransferPostTransformer;
use App\Filament\Resources\TransferPostResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = TransferPostResource::class;

    protected static string $permission = 'ViewAny:TransferPost';

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

        return TransferPostTransformer::collection($query);
    }
}
