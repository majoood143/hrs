<?php

namespace App\Filament\Resources\Api\PaymentGatewayLog\Handlers;

use App\Filament\Resources\Api\PaymentGatewayLog\Transformers\PaymentGatewayLogTransformer;
use App\Filament\Resources\PaymentGatewayLogResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = PaymentGatewayLogResource::class;

    protected static string $permission = 'View:PaymentGatewayLog';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new PaymentGatewayLogTransformer($query);
    }
}
