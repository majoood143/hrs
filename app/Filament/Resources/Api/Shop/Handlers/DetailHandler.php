<?php

namespace App\Filament\Resources\Api\Shop\Handlers;

use App\Filament\Resources\Api\Shop\Transformers\ShopTransformer;
use App\Filament\Resources\ShopResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = ShopResource::class;

    protected static string $permission = 'View:Shop';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new ShopTransformer($query);
    }
}
