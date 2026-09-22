<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Horse\Handlers;
use App\Filament\Resources\HorseResource;
use Rupadana\ApiService\ApiService;

class HorseApiService extends ApiService
{
    protected static ?string $resource = HorseResource::class;

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
