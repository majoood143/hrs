<?php

namespace App\Filament\Resources\Api\SuccessStory\Handlers;

use App\Filament\Resources\Api\SuccessStory\Transformers\SuccessStoryTransformer;
use App\Filament\Resources\SuccessStoryResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = SuccessStoryResource::class;

    protected static string $permission = 'View:SuccessStory';

    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = QueryBuilder::for(
            static::getEloquentQuery()->where(static::getKeyName(), $id)
        )->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new SuccessStoryTransformer($query);
    }
}
