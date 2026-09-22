<?php

namespace App\Filament\Resources\Api\Stable\Handlers;

use App\Filament\Resources\Api\Stable\Transformers\StableTransformer;
use App\Filament\Resources\StableResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = StableResource::class;

    protected static string $permission = 'View:Stable';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new StableTransformer($query);
    }
}
