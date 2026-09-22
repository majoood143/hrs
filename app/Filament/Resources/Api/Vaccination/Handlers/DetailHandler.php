<?php

namespace App\Filament\Resources\Api\Vaccination\Handlers;

use App\Filament\Resources\Api\Vaccination\Transformers\VaccinationTransformer;
use App\Filament\Resources\VaccinationResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = VaccinationResource::class;

    protected static string $permission = 'View:Vaccination';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new VaccinationTransformer($query);
    }
}
