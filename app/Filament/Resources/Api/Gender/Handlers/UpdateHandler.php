<?php

namespace App\Filament\Resources\Api\Gender\Handlers;

use App\Filament\Resources\Api\Gender\Requests\UpdateGenderRequest;
use App\Filament\Resources\GenderResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = GenderResource::class;

    protected static string $permission = 'Update:Gender';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateGenderRequest $request)
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
