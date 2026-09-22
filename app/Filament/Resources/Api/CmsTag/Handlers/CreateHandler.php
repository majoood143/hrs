<?php

namespace App\Filament\Resources\Api\CmsTag\Handlers;

use App\Filament\Resources\Api\CmsTag\Requests\CreateCmsTagRequest;
use App\Filament\Resources\CmsTagResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CmsTagResource::class;

    protected static string $permission = 'Create:CmsTag';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateCmsTagRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
