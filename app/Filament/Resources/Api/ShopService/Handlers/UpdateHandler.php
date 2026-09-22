<?php

namespace App\Filament\Resources\Api\ShopService\Handlers;

use App\Filament\Resources\Api\ShopService\Requests\UpdateShopServiceRequest;
use App\Filament\Resources\ShopServiceResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = ShopServiceResource::class;

    protected static string $permission = 'Update:ShopService';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateShopServiceRequest $request)
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
