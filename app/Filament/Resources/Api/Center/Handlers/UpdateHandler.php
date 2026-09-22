<?php

namespace App\Filament\Resources\Api\Center\Handlers;

use App\Filament\Resources\Api\Center\Requests\UpdateCenterRequest;
use App\Filament\Resources\CenterResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = CenterResource::class;

    protected static string $permission = 'Update:Center';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateCenterRequest $request)
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
