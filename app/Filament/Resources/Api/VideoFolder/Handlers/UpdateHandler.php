<?php

namespace App\Filament\Resources\Api\VideoFolder\Handlers;

use App\Filament\Resources\Api\VideoFolder\Requests\UpdateVideoFolderRequest;
use App\Filament\Resources\VideoFolderResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = VideoFolderResource::class;

    protected static string $permission = 'Update:VideoFolder';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateVideoFolderRequest $request)
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
