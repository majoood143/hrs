<?php

namespace App\Filament\Resources\Api\ServiceFeeSetting\Handlers;

use App\Filament\Resources\Api\ServiceFeeSetting\Transformers\ServiceFeeSettingTransformer;
use App\Filament\Resources\ServiceFeeSettingResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = ServiceFeeSettingResource::class;

    protected static string $permission = 'View:ServiceFeeSetting';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new ServiceFeeSettingTransformer($query);
    }
}
