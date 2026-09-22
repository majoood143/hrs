<?php

namespace App\Filament\Resources\Api\Service\Handlers;

use App\Filament\Resources\Api\Service\Requests\CreateServiceRequest;
use App\Filament\Resources\ServiceResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = ServiceResource::class;

    protected static string $permission = 'Create:Service';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateServiceRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
