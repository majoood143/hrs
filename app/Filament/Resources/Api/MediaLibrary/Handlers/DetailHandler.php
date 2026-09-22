<?php

namespace App\Filament\Resources\Api\MediaLibrary\Handlers;

use App\Filament\Resources\Api\MediaLibrary\Transformers\MediaLibraryTransformer;
use App\Filament\Resources\MediaLibraryResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = MediaLibraryResource::class;

    protected static string $permission = 'View:MediaLibrary';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new MediaLibraryTransformer($query);
    }
}
