<?php

namespace App\Filament\Resources\Api\StableService\Handlers;

use App\Filament\Resources\Api\StableService\Transformers\StableServiceTransformer;
use App\Filament\Resources\StableServiceResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = StableServiceResource::class;

    protected static string $permission = 'View:StableService';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new StableServiceTransformer($query);
    }
}
