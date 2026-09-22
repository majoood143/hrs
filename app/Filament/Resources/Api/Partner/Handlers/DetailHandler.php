<?php

namespace App\Filament\Resources\Api\Partner\Handlers;

use App\Filament\Resources\Api\Partner\Transformers\PartnerTransformer;
use App\Filament\Resources\PartnerResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = PartnerResource::class;

    protected static string $permission = 'View:Partner';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new PartnerTransformer($query);
    }
}
