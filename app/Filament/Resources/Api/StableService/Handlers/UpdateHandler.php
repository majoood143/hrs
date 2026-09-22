<?php

namespace App\Filament\Resources\Api\StableService\Handlers;

use App\Filament\Resources\Api\StableService\Requests\UpdateStableServiceRequest;
use App\Filament\Resources\StableServiceResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = StableServiceResource::class;

    protected static string $permission = 'Update:StableService';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateStableServiceRequest $request)
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
