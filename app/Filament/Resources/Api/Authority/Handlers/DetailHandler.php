<?php

namespace App\Filament\Resources\Api\Authority\Handlers;

use App\Filament\Resources\Api\Authority\Transformers\AuthorityTransformer;
use App\Filament\Resources\AuthorityResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = AuthorityResource::class;

    protected static string $permission = 'View:Authority';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new AuthorityTransformer($query);
    }
}
