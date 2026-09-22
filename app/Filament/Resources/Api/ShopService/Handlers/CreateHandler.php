<?php

namespace App\Filament\Resources\Api\ShopService\Handlers;

use App\Filament\Resources\Api\ShopService\Requests\CreateShopServiceRequest;
use App\Filament\Resources\ShopServiceResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = ShopServiceResource::class;

    protected static string $permission = 'Create:ShopService';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateShopServiceRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
