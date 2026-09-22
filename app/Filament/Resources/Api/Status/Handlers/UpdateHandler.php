<?php

namespace App\Filament\Resources\Api\Status\Handlers;

use App\Filament\Resources\Api\Status\Requests\UpdateStatusRequest;
use App\Filament\Resources\StatusResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = StatusResource::class;

    protected static string $permission = 'Update:Status';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateStatusRequest $request)
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
