<?php

namespace App\Filament\Resources\Api\Horse\Handlers;

use App\Filament\Resources\Api\Horse\Transformers\HorseTransformer;
use App\Filament\Resources\HorseResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = HorseResource::class;

    protected static string $permission = 'View:Horse';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new HorseTransformer($query);
    }
}
