<?php

namespace App\Filament\Resources\Api\ServiceOrder\Handlers;

use App\Filament\Resources\Api\ServiceOrder\Transformers\ServiceOrderTransformer;
use App\Filament\Resources\ServiceOrderResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = ServiceOrderResource::class;

    protected static string $permission = 'View:ServiceOrder';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new ServiceOrderTransformer($query);
    }
}
