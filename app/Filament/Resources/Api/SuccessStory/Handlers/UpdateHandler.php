<?php

namespace App\Filament\Resources\Api\SuccessStory\Handlers;

use App\Filament\Resources\Api\SuccessStory\Requests\UpdateSuccessStoryRequest;
use App\Filament\Resources\SuccessStoryResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = SuccessStoryResource::class;

    protected static string $permission = 'Update:SuccessStory';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateSuccessStoryRequest $request)
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
