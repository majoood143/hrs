<?php

namespace App\Filament\Resources\Api\Country\Handlers;

use App\Filament\Resources\Api\Country\Transformers\CountryTransformer;
use App\Filament\Resources\CountryResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = CountryResource::class;

    protected static string $permission = 'View:Country';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new CountryTransformer($query);
    }
}
