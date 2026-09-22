<?php

namespace App\Filament\Resources\Api\CenterService\Handlers;

use App\Filament\Resources\Api\CenterService\Requests\UpdateCenterServiceRequest;
use App\Filament\Resources\CenterServiceResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = CenterServiceResource::class;

    protected static string $permission = 'Update:CenterService';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateCenterServiceRequest $request)
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
