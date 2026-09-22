<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\SuccessStory\Handlers;
use App\Filament\Resources\SuccessStoryResource;
use Rupadana\ApiService\ApiService;

class SuccessStoryApiService extends ApiService
{
    protected static ?string $resource = SuccessStoryResource::class;

    public static function handlers(): array
    {
        return [
            Handlers\CreateHandler::class,
            Handlers\UpdateHandler::class,
            Handlers\DeleteHandler::class,
            Handlers\PaginationHandler::class,
            Handlers\DetailHandler::class,
        ];
    }
}
