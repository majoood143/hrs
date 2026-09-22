<?php

namespace App\Filament\Resources\Api\Color\Handlers;

use App\Filament\Resources\Api\Color\Requests\UpdateColorRequest;
use App\Filament\Resources\ColorResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = ColorResource::class;

    protected static string $permission = 'Update:Color';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateColorRequest $request)
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
