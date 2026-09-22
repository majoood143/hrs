<?php

namespace App\Filament\Resources\Api\Setting\Handlers;

use App\Filament\Resources\Api\Setting\Transformers\SettingTransformer;
use App\Filament\Resources\SettingResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = SettingResource::class;

    protected static string $permission = 'View:Setting';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new SettingTransformer($query);
    }
}
