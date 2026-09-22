<?php

namespace App\Filament\Resources\Api\Vaccination\Handlers;

use App\Filament\Resources\Api\Vaccination\Requests\CreateVaccinationRequest;
use App\Filament\Resources\VaccinationResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = VaccinationResource::class;

    protected static string $permission = 'Create:Vaccination';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateVaccinationRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
