<?php

namespace App\Filament\Resources\Api\Event\Handlers;

use App\Filament\Resources\Api\Event\Transformers\EventTransformer;
use App\Filament\Resources\EventResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = EventResource::class;

    protected static string $permission = 'View:Event';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new EventTransformer($query);
    }
}
