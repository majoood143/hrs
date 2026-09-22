<?php

namespace App\Filament\Resources\Api\Shop\Handlers;

use App\Filament\Resources\Api\Shop\Requests\CreateShopRequest;
use App\Filament\Resources\ShopResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = ShopResource::class;

    protected static string $permission = 'Create:Shop';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateShopRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
