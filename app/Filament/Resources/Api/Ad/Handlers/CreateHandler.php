<?php

namespace App\Filament\Resources\Api\Ad\Handlers;

use App\Filament\Resources\AdResource;
use App\Filament\Resources\Api\Ad\Requests\CreateAdRequest;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = AdResource::class;

    protected static string $permission = 'Create:Ad';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateAdRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
