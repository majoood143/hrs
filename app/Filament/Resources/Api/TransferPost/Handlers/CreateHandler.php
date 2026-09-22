<?php

namespace App\Filament\Resources\Api\TransferPost\Handlers;

use App\Filament\Resources\Api\TransferPost\Requests\CreateTransferPostRequest;
use App\Filament\Resources\TransferPostResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = TransferPostResource::class;

    protected static string $permission = 'Create:TransferPost';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateTransferPostRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
