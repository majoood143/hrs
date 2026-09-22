<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Video\Handlers;
use App\Filament\Resources\VideoResource;
use Rupadana\ApiService\ApiService;

class VideoApiService extends ApiService
{
    protected static ?string $resource = VideoResource::class;

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
