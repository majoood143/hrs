<?php

namespace App\Filament\Resources\Api\Ad\Handlers;

use App\Filament\Resources\AdResource;
use App\Filament\Resources\Api\Ad\Transformers\AdTransformer;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = AdResource::class;

    protected static string $permission = 'View:Ad';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new AdTransformer($query);
    }
}
