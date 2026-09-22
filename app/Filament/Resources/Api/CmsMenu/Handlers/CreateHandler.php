<?php

namespace App\Filament\Resources\Api\CmsMenu\Handlers;

use App\Filament\Resources\Api\CmsMenu\Requests\CreateCmsMenuRequest;
use App\Filament\Resources\CmsMenuResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CmsMenuResource::class;

    protected static string $permission = 'Create:CmsMenu';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateCmsMenuRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
