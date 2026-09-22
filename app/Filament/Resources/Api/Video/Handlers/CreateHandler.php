<?php

namespace App\Filament\Resources\Api\Video\Handlers;

use App\Filament\Resources\Api\Video\Requests\CreateVideoRequest;
use App\Filament\Resources\VideoResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = VideoResource::class;

    protected static string $permission = 'Create:Video';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateVideoRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
