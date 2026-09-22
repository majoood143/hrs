<?php

namespace App\Filament\Resources\Api\Farrier\Handlers;

use App\Filament\Resources\Api\Farrier\Transformers\FarrierTransformer;
use App\Filament\Resources\FarrierResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = FarrierResource::class;

    protected static string $permission = 'View:Farrier';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new FarrierTransformer($query);
    }
}
