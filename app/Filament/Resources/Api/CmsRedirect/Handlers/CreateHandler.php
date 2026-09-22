<?php

namespace App\Filament\Resources\Api\CmsRedirect\Handlers;

use App\Filament\Resources\Api\CmsRedirect\Requests\CreateCmsRedirectRequest;
use App\Filament\Resources\CmsRedirectResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CmsRedirectResource::class;

    protected static string $permission = 'Create:CmsRedirect';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateCmsRedirectRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
