<?php

namespace App\Filament\Resources\Api\Authority\Handlers;

use App\Filament\Resources\Api\Authority\Requests\CreateAuthorityRequest;
use App\Filament\Resources\AuthorityResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = AuthorityResource::class;

    protected static string $permission = 'Create:Authority';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateAuthorityRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
