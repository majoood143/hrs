<?php

namespace App\Filament\Resources\Api\HorseSalePost\Handlers;

use App\Filament\Resources\Api\HorseSalePost\Requests\UpdateHorseSalePostRequest;
use App\Filament\Resources\HorseSalePostResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = HorseSalePostResource::class;

    protected static string $permission = 'Update:HorseSalePost';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateHorseSalePostRequest $request)
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
