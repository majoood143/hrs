<?php

namespace App\Filament\Resources\Api\Service\Handlers;

use App\Filament\Resources\Api\Service\Requests\UpdateServiceRequest;
use App\Filament\Resources\ServiceResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = ServiceResource::class;

    protected static string $permission = 'Update:Service';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateServiceRequest $request)
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
