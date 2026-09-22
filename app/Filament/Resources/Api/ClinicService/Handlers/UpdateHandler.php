<?php

namespace App\Filament\Resources\Api\ClinicService\Handlers;

use App\Filament\Resources\Api\ClinicService\Requests\UpdateClinicServiceRequest;
use App\Filament\Resources\ClinicServiceResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = ClinicServiceResource::class;

    protected static string $permission = 'Update:ClinicService';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateClinicServiceRequest $request)
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
