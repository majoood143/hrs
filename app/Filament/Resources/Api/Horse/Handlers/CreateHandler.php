<?php

namespace App\Filament\Resources\Api\Horse\Handlers;

use App\Filament\Resources\Api\Horse\Requests\CreateHorseRequest;
use App\Filament\Resources\HorseResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = HorseResource::class;

    protected static string $permission = 'Create:Horse';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateHorseRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
