<?php

namespace App\Filament\Resources\Api\EventCategory\Handlers;

use App\Filament\Resources\Api\EventCategory\Requests\CreateEventCategoryRequest;
use App\Filament\Resources\EventCategoryResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = EventCategoryResource::class;

    protected static string $permission = 'Create:EventCategory';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateEventCategoryRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
