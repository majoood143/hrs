<?php

namespace App\Filament\Resources\Api\Service\Handlers;

use App\Filament\Resources\Api\Service\Transformers\ServiceTransformer;
use App\Filament\Resources\ServiceResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = ServiceResource::class;

    protected static string $permission = 'View:Service';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new ServiceTransformer($query);
    }
}
