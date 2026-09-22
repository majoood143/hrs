<?php

namespace App\Filament\Resources\Api\Video\Handlers;

use App\Filament\Resources\Api\Video\Requests\UpdateVideoRequest;
use App\Filament\Resources\VideoResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = VideoResource::class;

    protected static string $permission = 'Update:Video';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateVideoRequest $request)
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
