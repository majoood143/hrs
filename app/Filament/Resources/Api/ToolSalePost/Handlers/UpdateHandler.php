<?php

namespace App\Filament\Resources\Api\ToolSalePost\Handlers;

use App\Filament\Resources\Api\ToolSalePost\Requests\UpdateToolSalePostRequest;
use App\Filament\Resources\ToolSalePostResource;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = ToolSalePostResource::class;

    protected static string $permission = 'Update:ToolSalePost';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public function handler(UpdateToolSalePostRequest $request)
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
