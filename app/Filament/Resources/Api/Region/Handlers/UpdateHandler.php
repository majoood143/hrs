<?php

namespace App\Filament\Resources\Api\Region\Handlers;

use App\Filament\Resources\Api\Region\Requests\UpdateRegionRequest;
use App\Filament\Resources\RegionResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = RegionResource::class;

    protected static string $permission = 'Update:Region';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateRegionRequest $request)
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
