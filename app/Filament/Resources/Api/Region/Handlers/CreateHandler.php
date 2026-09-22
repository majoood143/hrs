<?php

namespace App\Filament\Resources\Api\Region\Handlers;

use App\Filament\Resources\Api\Region\Requests\CreateRegionRequest;
use App\Filament\Resources\RegionResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = RegionResource::class;

    protected static string $permission = 'Create:Region';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateRegionRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
