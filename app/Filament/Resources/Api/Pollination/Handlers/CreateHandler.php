<?php

namespace App\Filament\Resources\Api\Pollination\Handlers;

use App\Filament\Resources\Api\Pollination\Requests\CreatePollinationRequest;
use App\Filament\Resources\PollinationResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = PollinationResource::class;

    protected static string $permission = 'Create:Pollination';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreatePollinationRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
