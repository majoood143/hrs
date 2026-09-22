<?php

namespace App\Filament\Resources\Api\CenterService\Handlers;

use App\Filament\Resources\Api\CenterService\Transformers\CenterServiceTransformer;
use App\Filament\Resources\CenterServiceResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = CenterServiceResource::class;

    protected static string $permission = 'View:CenterService';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new CenterServiceTransformer($query);
    }
}
