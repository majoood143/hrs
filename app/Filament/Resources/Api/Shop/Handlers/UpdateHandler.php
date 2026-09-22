<?php

namespace App\Filament\Resources\Api\Shop\Handlers;

use App\Filament\Resources\Api\Shop\Requests\UpdateShopRequest;
use App\Filament\Resources\ShopResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = ShopResource::class;

    protected static string $permission = 'Update:Shop';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateShopRequest $request)
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
