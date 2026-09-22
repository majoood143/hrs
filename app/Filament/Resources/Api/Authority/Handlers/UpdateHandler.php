<?php

namespace App\Filament\Resources\Api\Authority\Handlers;

use App\Filament\Resources\Api\Authority\Requests\UpdateAuthorityRequest;
use App\Filament\Resources\AuthorityResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = AuthorityResource::class;

    protected static string $permission = 'Update:Authority';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateAuthorityRequest $request)
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
