<?php

namespace App\Filament\Resources\Api\CmsCategory\Handlers;

use App\Filament\Resources\Api\CmsCategory\Requests\CreateCmsCategoryRequest;
use App\Filament\Resources\CmsCategoryResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CmsCategoryResource::class;

    protected static string $permission = 'Create:CmsCategory';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateCmsCategoryRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
