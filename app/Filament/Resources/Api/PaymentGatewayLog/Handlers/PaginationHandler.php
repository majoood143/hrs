<?php

namespace App\Filament\Resources\Api\PaymentGatewayLog\Handlers;

use App\Filament\Resources\Api\PaymentGatewayLog\Transformers\PaymentGatewayLogTransformer;
use App\Filament\Resources\PaymentGatewayLogResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = PaymentGatewayLogResource::class;

    protected static string $permission = 'ViewAny:PaymentGatewayLog';

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

        return PaymentGatewayLogTransformer::collection($query);
    }
}
