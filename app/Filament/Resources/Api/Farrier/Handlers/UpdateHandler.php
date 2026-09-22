<?php

namespace App\Filament\Resources\Api\Farrier\Handlers;

use App\Filament\Resources\Api\Farrier\Requests\UpdateFarrierRequest;
use App\Filament\Resources\FarrierResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = FarrierResource::class;

    protected static string $permission = 'Update:Farrier';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateFarrierRequest $request)
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
