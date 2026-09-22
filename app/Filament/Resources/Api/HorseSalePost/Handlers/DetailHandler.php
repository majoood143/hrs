<?php

namespace App\Filament\Resources\Api\HorseSalePost\Handlers;

use App\Filament\Resources\Api\HorseSalePost\Transformers\HorseSalePostTransformer;
use App\Filament\Resources\HorseSalePostResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = HorseSalePostResource::class;

    protected static string $permission = 'View:HorseSalePost';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new HorseSalePostTransformer($query);
    }
}
