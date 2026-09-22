<?php

namespace App\Filament\Resources\Api\Type\Handlers;

use App\Filament\Resources\Api\Type\Requests\UpdateTypeRequest;
use App\Filament\Resources\TypeResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = TypeResource::class;

    protected static string $permission = 'Update:Type';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateTypeRequest $request)
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
