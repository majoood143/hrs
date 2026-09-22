<?php

namespace App\Filament\Resources\Api\Horse\Handlers;

use App\Filament\Resources\Api\Horse\Requests\UpdateHorseRequest;
use App\Filament\Resources\HorseResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = HorseResource::class;

    protected static string $permission = 'Update:Horse';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateHorseRequest $request)
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
