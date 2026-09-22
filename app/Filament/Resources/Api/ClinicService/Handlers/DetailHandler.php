<?php

namespace App\Filament\Resources\Api\ClinicService\Handlers;

use App\Filament\Resources\Api\ClinicService\Transformers\ClinicServiceTransformer;
use App\Filament\Resources\ClinicServiceResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = ClinicServiceResource::class;

    protected static string $permission = 'View:ClinicService';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new ClinicServiceTransformer($query);
    }
}
