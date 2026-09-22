<?php

namespace App\Filament\Resources\Api\Event\Handlers;

use App\Filament\Resources\Api\Event\Requests\CreateEventRequest;
use App\Filament\Resources\EventResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = EventResource::class;

    protected static string $permission = 'Create:Event';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateEventRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
