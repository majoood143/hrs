<?php

namespace App\Filament\Resources\Api\CmsMenu\Handlers;

use App\Filament\Resources\Api\CmsMenu\Requests\UpdateCmsMenuRequest;
use App\Filament\Resources\CmsMenuResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = CmsMenuResource::class;

    protected static string $permission = 'Update:CmsMenu';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateCmsMenuRequest $request)
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
