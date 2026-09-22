<?php

namespace App\Filament\Resources\Api\ToolSalePost\Handlers;

use App\Filament\Resources\Api\ToolSalePost\Transformers\ToolSalePostTransformer;
use App\Filament\Resources\ToolSalePostResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = ToolSalePostResource::class;

    protected static string $permission = 'View:ToolSalePost';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new ToolSalePostTransformer($query);
    }
}
