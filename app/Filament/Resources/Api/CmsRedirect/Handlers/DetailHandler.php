<?php

namespace App\Filament\Resources\Api\CmsRedirect\Handlers;

use App\Filament\Resources\Api\CmsRedirect\Transformers\CmsRedirectTransformer;
use App\Filament\Resources\CmsRedirectResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = CmsRedirectResource::class;

    protected static string $permission = 'View:CmsRedirect';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new CmsRedirectTransformer($query);
    }
}
