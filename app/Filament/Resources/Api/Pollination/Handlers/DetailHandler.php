<?php

namespace App\Filament\Resources\Api\Pollination\Handlers;

use App\Filament\Resources\Api\Pollination\Transformers\PollinationTransformer;
use App\Filament\Resources\PollinationResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = PollinationResource::class;

    protected static string $permission = 'View:Pollination';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new PollinationTransformer($query);
    }
}
