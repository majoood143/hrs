<?php

namespace App\Filament\Resources\Api\Clinic\Handlers;

use App\Filament\Resources\Api\Clinic\Transformers\ClinicTransformer;
use App\Filament\Resources\ClinicResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = ClinicResource::class;

    protected static string $permission = 'View:Clinic';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new ClinicTransformer($query);
    }
}
