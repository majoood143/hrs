<?php

namespace App\Filament\Resources\Api\CenterService\Handlers;

use App\Filament\Resources\Api\CenterService\Requests\CreateCenterServiceRequest;
use App\Filament\Resources\CenterServiceResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CenterServiceResource::class;

    protected static string $permission = 'Create:CenterService';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateCenterServiceRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
