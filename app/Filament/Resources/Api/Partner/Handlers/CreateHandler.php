<?php

namespace App\Filament\Resources\Api\Partner\Handlers;

use App\Filament\Resources\Api\Partner\Requests\CreatePartnerRequest;
use App\Filament\Resources\PartnerResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = PartnerResource::class;

    protected static string $permission = 'Create:Partner';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreatePartnerRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
