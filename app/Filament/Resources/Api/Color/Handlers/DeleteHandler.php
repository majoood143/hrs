<?php

namespace App\Filament\Resources\Api\Color\Handlers;

use App\Filament\Resources\ColorResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;

class DeleteHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = ColorResource::class;

    protected static string $permission = 'Delete:Color';

    public static function getMethod()
    {
        return Handlers::DELETE;
    }

    public function handler(Request $request)
    {
        $id = $request->route('id');
        $model = static::getModel()::find($id);

        if (! $model) {
            return static::sendNotFoundResponse();
        }

        $model->delete();

        return static::sendSuccessResponse($model, 'Successfully Delete Resource');
    }
}
