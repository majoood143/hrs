<?php

namespace App\Filament\Resources\Api\Partner\Handlers;

use App\Filament\Resources\Api\Partner\Requests\UpdatePartnerRequest;
use App\Filament\Resources\PartnerResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = PartnerResource::class;

    protected static string $permission = 'Update:Partner';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdatePartnerRequest $request)
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
