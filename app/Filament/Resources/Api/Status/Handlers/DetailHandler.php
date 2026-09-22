<?php

namespace App\Filament\Resources\Api\Status\Handlers;

use App\Filament\Resources\Api\Status\Transformers\StatusTransformer;
use App\Filament\Resources\StatusResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = StatusResource::class;

    protected static string $permission = 'View:Status';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new StatusTransformer($query);
    }
}
