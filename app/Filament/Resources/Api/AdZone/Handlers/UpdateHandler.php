<?php

namespace App\Filament\Resources\Api\AdZone\Handlers;

use App\Filament\Resources\AdZoneResource;
use App\Filament\Resources\Api\AdZone\Requests\UpdateAdZoneRequest;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = AdZoneResource::class;

    protected static string $permission = 'Update:AdZone';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateAdZoneRequest $request)
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
