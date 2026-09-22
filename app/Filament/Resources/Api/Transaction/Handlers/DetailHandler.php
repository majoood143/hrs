<?php

namespace App\Filament\Resources\Api\Transaction\Handlers;

use App\Filament\Resources\Api\Transaction\Transformers\TransactionTransformer;
use App\Filament\Resources\TransactionResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = TransactionResource::class;

    protected static string $permission = 'View:Transaction';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new TransactionTransformer($query);
    }
}
