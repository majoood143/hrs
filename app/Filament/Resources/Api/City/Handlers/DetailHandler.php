<?php

namespace App\Filament\Resources\Api\City\Handlers;

use App\Filament\Resources\Api\City\Transformers\CityTransformer;
use App\Filament\Resources\CityResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = CityResource::class;

    protected static string $permission = 'View:City';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new CityTransformer($query);
    }
}
