<?php

namespace App\Filament\Resources\Api\Country\Handlers;

use App\Filament\Resources\Api\Country\Requests\CreateCountryRequest;
use App\Filament\Resources\CountryResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CountryResource::class;

    protected static string $permission = 'Create:Country';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateCountryRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
