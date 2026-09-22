<?php

namespace App\Filament\Resources\Api\Attachement\Handlers;

use App\Filament\Resources\Api\Attachement\Requests\CreateAttachementRequest;
use App\Filament\Resources\AttachementResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = AttachementResource::class;

    protected static string $permission = 'Create:Attachement';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateAttachementRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
