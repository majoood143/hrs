<?php

namespace App\Filament\Resources\Api\Type\Handlers;

use App\Filament\Resources\Api\Type\Requests\CreateTypeRequest;
use App\Filament\Resources\TypeResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = TypeResource::class;

    protected static string $permission = 'Create:Type';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateTypeRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
