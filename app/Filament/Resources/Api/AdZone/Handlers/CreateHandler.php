<?php

namespace App\Filament\Resources\Api\AdZone\Handlers;

use App\Filament\Resources\AdZoneResource;
use App\Filament\Resources\Api\AdZone\Requests\CreateAdZoneRequest;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = AdZoneResource::class;

    protected static string $permission = 'Create:AdZone';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateAdZoneRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
