<?php

namespace App\Filament\Resources\Api\Customer\Handlers;

use App\Filament\Resources\Api\Customer\Transformers\CustomerTransformer;
use App\Filament\Resources\CustomerResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = CustomerResource::class;

    protected static string $permission = 'View:Customer';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new CustomerTransformer($query);
    }
}
