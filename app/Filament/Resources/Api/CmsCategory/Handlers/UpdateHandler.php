<?php

namespace App\Filament\Resources\Api\CmsCategory\Handlers;

use App\Filament\Resources\Api\CmsCategory\Requests\UpdateCmsCategoryRequest;
use App\Filament\Resources\CmsCategoryResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = CmsCategoryResource::class;

    protected static string $permission = 'Update:CmsCategory';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateCmsCategoryRequest $request)
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
