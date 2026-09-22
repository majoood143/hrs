<?php

namespace App\Filament\Resources\Api\Status\Handlers;

use App\Filament\Resources\Api\Status\Requests\CreateStatusRequest;
use App\Filament\Resources\StatusResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = StatusResource::class;

    protected static string $permission = 'Create:Status';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateStatusRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
