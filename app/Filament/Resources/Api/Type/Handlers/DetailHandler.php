<?php

namespace App\Filament\Resources\Api\Type\Handlers;

use App\Filament\Resources\Api\Type\Transformers\TypeTransformer;
use App\Filament\Resources\TypeResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = TypeResource::class;

    protected static string $permission = 'View:Type';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new TypeTransformer($query);
    }
}
