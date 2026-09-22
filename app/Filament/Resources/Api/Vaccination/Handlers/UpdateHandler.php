<?php

namespace App\Filament\Resources\Api\Vaccination\Handlers;

use App\Filament\Resources\Api\Vaccination\Requests\UpdateVaccinationRequest;
use App\Filament\Resources\VaccinationResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = VaccinationResource::class;

    protected static string $permission = 'Update:Vaccination';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateVaccinationRequest $request)
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
