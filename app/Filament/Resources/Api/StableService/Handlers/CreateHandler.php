<?php

namespace App\Filament\Resources\Api\StableService\Handlers;

use App\Filament\Resources\Api\StableService\Requests\CreateStableServiceRequest;
use App\Filament\Resources\StableServiceResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = StableServiceResource::class;

    protected static string $permission = 'Create:StableService';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateStableServiceRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
