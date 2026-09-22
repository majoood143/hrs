<?php

namespace App\Filament\Resources\Api\Stable\Handlers;

use App\Filament\Resources\Api\Stable\Requests\UpdateStableRequest;
use App\Filament\Resources\StableResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = StableResource::class;

    protected static string $permission = 'Update:Stable';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateStableRequest $request)
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
