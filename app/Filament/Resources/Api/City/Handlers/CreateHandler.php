<?php

namespace App\Filament\Resources\Api\City\Handlers;

use App\Filament\Resources\Api\City\Requests\CreateCityRequest;
use App\Filament\Resources\CityResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CityResource::class;

    protected static string $permission = 'Create:City';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateCityRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
