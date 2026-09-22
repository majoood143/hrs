<?php

namespace App\Filament\Resources\Api\ClinicService\Handlers;

use App\Filament\Resources\Api\ClinicService\Requests\CreateClinicServiceRequest;
use App\Filament\Resources\ClinicServiceResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = ClinicServiceResource::class;

    protected static string $permission = 'Create:ClinicService';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateClinicServiceRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
