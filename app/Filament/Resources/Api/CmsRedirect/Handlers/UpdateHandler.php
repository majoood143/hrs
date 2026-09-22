<?php

namespace App\Filament\Resources\Api\CmsRedirect\Handlers;

use App\Filament\Resources\Api\CmsRedirect\Requests\UpdateCmsRedirectRequest;
use App\Filament\Resources\CmsRedirectResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = CmsRedirectResource::class;

    protected static string $permission = 'Update:CmsRedirect';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateCmsRedirectRequest $request)
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
