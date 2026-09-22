<?php

namespace App\Filament\Resources\Api\TransferPost\Handlers;

use App\Filament\Resources\Api\TransferPost\Requests\UpdateTransferPostRequest;
use App\Filament\Resources\TransferPostResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = TransferPostResource::class;

    protected static string $permission = 'Update:TransferPost';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateTransferPostRequest $request)
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
