<?php

namespace App\Filament\Resources\Api\Farrier\Handlers;

use App\Filament\Resources\Api\Farrier\Requests\CreateFarrierRequest;
use App\Filament\Resources\FarrierResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = FarrierResource::class;

    protected static string $permission = 'Create:Farrier';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateFarrierRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
