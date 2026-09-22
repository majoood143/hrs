<?php

namespace App\Filament\Resources\Api\Clinic\Handlers;

use App\Filament\Resources\Api\Clinic\Requests\CreateClinicRequest;
use App\Filament\Resources\ClinicResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = ClinicResource::class;

    protected static string $permission = 'Create:Clinic';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateClinicRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
