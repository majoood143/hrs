<?php

namespace App\Filament\Resources\Api\Clinic\Handlers;

use App\Filament\Resources\Api\Clinic\Requests\UpdateClinicRequest;
use App\Filament\Resources\ClinicResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = ClinicResource::class;

    protected static string $permission = 'Update:Clinic';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateClinicRequest $request)
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
