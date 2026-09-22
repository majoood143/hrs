<?php

namespace App\Filament\Resources\Api\Transaction\Handlers;

use App\Filament\Resources\Api\Transaction\Transformers\TransactionTransformer;
use App\Filament\Resources\TransactionResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = TransactionResource::class;

    protected static string $permission = 'ViewAny:Transaction';

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

        return TransactionTransformer::collection($query);
    }
}
