<?php

namespace App\Filament\Resources\Api\HorseSalePost\Handlers;

use App\Filament\Resources\Api\HorseSalePost\Requests\CreateHorseSalePostRequest;
use App\Filament\Resources\HorseSalePostResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = HorseSalePostResource::class;

    protected static string $permission = 'Create:HorseSalePost';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateHorseSalePostRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
