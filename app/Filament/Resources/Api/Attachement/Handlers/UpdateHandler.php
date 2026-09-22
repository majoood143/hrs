<?php

namespace App\Filament\Resources\Api\Attachement\Handlers;

use App\Filament\Resources\Api\Attachement\Requests\UpdateAttachementRequest;
use App\Filament\Resources\AttachementResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = AttachementResource::class;

    protected static string $permission = 'Update:Attachement';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateAttachementRequest $request)
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
