<?php

namespace App\Filament\Resources\Api\Center\Handlers;

use App\Filament\Resources\Api\Center\Requests\CreateCenterRequest;
use App\Filament\Resources\CenterResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CenterResource::class;

    protected static string $permission = 'Create:Center';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateCenterRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
