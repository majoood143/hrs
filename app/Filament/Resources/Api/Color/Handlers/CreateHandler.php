<?php

namespace App\Filament\Resources\Api\Color\Handlers;

use App\Filament\Resources\Api\Color\Requests\CreateColorRequest;
use App\Filament\Resources\ColorResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = ColorResource::class;

    protected static string $permission = 'Create:Color';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateColorRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
