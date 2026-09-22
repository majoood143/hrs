<?php

namespace App\Filament\Resources\Api\City\Handlers;

use App\Filament\Resources\Api\City\Requests\UpdateCityRequest;
use App\Filament\Resources\CityResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = CityResource::class;

    protected static string $permission = 'Update:City';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateCityRequest $request)
    {
        $id = $request->route('id');
        $model = static::getModel()::find($id);

        if (! $model) {
            return static::sendNotFoundResponse();
        }

        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Update Resource');
    }
}
