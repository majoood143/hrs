<?php

namespace App\Filament\Resources\Api\ToolSalePost\Handlers;

use App\Filament\Resources\Api\ToolSalePost\Requests\CreateToolSalePostRequest;
use App\Filament\Resources\ToolSalePostResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = ToolSalePostResource::class;

    protected static string $permission = 'Create:ToolSalePost';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateToolSalePostRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
