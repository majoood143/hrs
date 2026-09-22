<?php

namespace App\Filament\Resources\Api\CmsPost\Handlers;

use App\Filament\Resources\Api\CmsPost\Requests\UpdateCmsPostRequest;
use App\Filament\Resources\CmsPostResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = CmsPostResource::class;

    protected static string $permission = 'Update:CmsPost';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateCmsPostRequest $request)
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
