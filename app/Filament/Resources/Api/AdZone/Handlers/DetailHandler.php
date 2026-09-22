<?php

namespace App\Filament\Resources\Api\AdZone\Handlers;

use App\Filament\Resources\AdZoneResource;
use App\Filament\Resources\Api\AdZone\Transformers\AdZoneTransformer;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = AdZoneResource::class;

    protected static string $permission = 'View:AdZone';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new AdZoneTransformer($query);
    }
}
