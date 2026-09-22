<?php

namespace App\Filament\Resources\Api\Ad\Handlers;

use App\Filament\Resources\AdResource;
use App\Filament\Resources\Api\Ad\Requests\UpdateAdRequest;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = AdResource::class;

    protected static string $permission = 'Update:Ad';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateAdRequest $request)
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
