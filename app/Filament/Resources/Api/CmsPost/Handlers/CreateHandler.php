<?php

namespace App\Filament\Resources\Api\CmsPost\Handlers;

use App\Filament\Resources\Api\CmsPost\Requests\CreateCmsPostRequest;
use App\Filament\Resources\CmsPostResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CmsPostResource::class;

    protected static string $permission = 'Create:CmsPost';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateCmsPostRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
