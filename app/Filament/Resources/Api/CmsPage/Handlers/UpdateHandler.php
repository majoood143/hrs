<?php

namespace App\Filament\Resources\Api\CmsPage\Handlers;

use App\Filament\Resources\Api\CmsPage\Requests\UpdateCmsPageRequest;
use App\Filament\Resources\CmsPageResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = CmsPageResource::class;

    protected static string $permission = 'Update:CmsPage';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateCmsPageRequest $request)
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
