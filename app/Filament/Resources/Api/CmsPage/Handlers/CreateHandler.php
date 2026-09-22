<?php

namespace App\Filament\Resources\Api\CmsPage\Handlers;

use App\Filament\Resources\Api\CmsPage\Requests\CreateCmsPageRequest;
use App\Filament\Resources\CmsPageResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CmsPageResource::class;

    protected static string $permission = 'Create:CmsPage';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateCmsPageRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
