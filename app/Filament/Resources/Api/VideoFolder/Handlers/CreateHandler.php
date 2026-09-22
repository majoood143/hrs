<?php

namespace App\Filament\Resources\Api\VideoFolder\Handlers;

use App\Filament\Resources\Api\VideoFolder\Requests\CreateVideoFolderRequest;
use App\Filament\Resources\VideoFolderResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = VideoFolderResource::class;

    protected static string $permission = 'Create:VideoFolder';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateVideoFolderRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
