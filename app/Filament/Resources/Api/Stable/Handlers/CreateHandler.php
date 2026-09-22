<?php

namespace App\Filament\Resources\Api\Stable\Handlers;

use App\Filament\Resources\Api\Stable\Requests\CreateStableRequest;
use App\Filament\Resources\StableResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = StableResource::class;

    protected static string $permission = 'Create:Stable';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateStableRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
