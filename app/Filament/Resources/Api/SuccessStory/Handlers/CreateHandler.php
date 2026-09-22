<?php

namespace App\Filament\Resources\Api\SuccessStory\Handlers;

use App\Filament\Resources\Api\SuccessStory\Requests\CreateSuccessStoryRequest;
use App\Filament\Resources\SuccessStoryResource;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = SuccessStoryResource::class;

    protected static string $permission = 'Create:SuccessStory';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public function handler(CreateSuccessStoryRequest $request)
    {
        $model = new (static::getModel());
        $model->fill($request->validated());
        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
