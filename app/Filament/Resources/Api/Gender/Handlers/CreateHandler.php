<?php

namespace App\Filament\Resources\Api\Gender\Handlers;

use App\Filament\Resources\Api\Gender\Requests\CreateGenderRequest;
use App\Filament\Resources\GenderResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = GenderResource::class;

    protected static string $permission = 'Create:Gender';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateGenderRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
