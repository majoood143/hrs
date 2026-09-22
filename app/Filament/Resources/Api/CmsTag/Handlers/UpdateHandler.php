<?php

namespace App\Filament\Resources\Api\CmsTag\Handlers;

use App\Filament\Resources\Api\CmsTag\Requests\UpdateCmsTagRequest;
use App\Filament\Resources\CmsTagResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = CmsTagResource::class;

    protected static string $permission = 'Update:CmsTag';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateCmsTagRequest $request)
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
