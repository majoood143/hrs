<?php

namespace App\Filament\Resources\Api\Attachement\Handlers;

use App\Filament\Resources\Api\Attachement\Transformers\AttachementTransformer;
use App\Filament\Resources\AttachementResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = AttachementResource::class;

    protected static string $permission = 'View:Attachement';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new AttachementTransformer($query);
    }
}
