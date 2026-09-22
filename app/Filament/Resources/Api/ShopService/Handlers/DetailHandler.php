<?php

namespace App\Filament\Resources\Api\ShopService\Handlers;

use App\Filament\Resources\Api\ShopService\Transformers\ShopServiceTransformer;
use App\Filament\Resources\ShopServiceResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = ShopServiceResource::class;

    protected static string $permission = 'View:ShopService';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new ShopServiceTransformer($query);
    }
}
