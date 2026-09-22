<?php

namespace App\Filament\Resources\Api\Country\Handlers;

use App\Filament\Resources\Api\Country\Requests\UpdateCountryRequest;
use App\Filament\Resources\CountryResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = CountryResource::class;

    protected static string $permission = 'Update:Country';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateCountryRequest $request)
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
