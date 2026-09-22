<?php

namespace App\Filament\Resources\Api\CmsPage\Handlers;

use App\Filament\Resources\Api\CmsPage\Transformers\CmsPageTransformer;
use App\Filament\Resources\CmsPageResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = CmsPageResource::class;

    protected static string $permission = 'View:CmsPage';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new CmsPageTransformer($query);
    }
}
