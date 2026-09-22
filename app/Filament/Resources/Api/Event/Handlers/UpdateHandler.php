<?php

namespace App\Filament\Resources\Api\Event\Handlers;

use App\Filament\Resources\Api\Event\Requests\UpdateEventRequest;
use App\Filament\Resources\EventResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = EventResource::class;

    protected static string $permission = 'Update:Event';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateEventRequest $request)
    {
        $id = $request->route('id');
        $model = static::getModel()::find($id);

        if (! $model) {
            return static::sendNotFoundResponse();
        }

        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Update Resource');
    }
}
