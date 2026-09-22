<?php

namespace App\Filament\Resources\Api\CmsMenu\Handlers;

use App\Filament\Resources\Api\CmsMenu\Transformers\CmsMenuTransformer;
use App\Filament\Resources\CmsMenuResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = CmsMenuResource::class;

    protected static string $permission = 'View:CmsMenu';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new CmsMenuTransformer($query);
    }
}
