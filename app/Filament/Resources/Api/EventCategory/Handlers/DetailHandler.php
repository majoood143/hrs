<?php

namespace App\Filament\Resources\Api\EventCategory\Handlers;

use App\Filament\Resources\Api\EventCategory\Transformers\EventCategoryTransformer;
use App\Filament\Resources\EventCategoryResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = EventCategoryResource::class;

    protected static string $permission = 'View:EventCategory';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new EventCategoryTransformer($query);
    }
}
