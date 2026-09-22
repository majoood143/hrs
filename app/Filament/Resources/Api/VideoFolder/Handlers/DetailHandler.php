<?php

namespace App\Filament\Resources\Api\VideoFolder\Handlers;

use App\Filament\Resources\Api\VideoFolder\Transformers\VideoFolderTransformer;
use App\Filament\Resources\VideoFolderResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = VideoFolderResource::class;

    protected static string $permission = 'View:VideoFolder';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new VideoFolderTransformer($query);
    }
}
