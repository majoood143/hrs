<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Pollination\Handlers;
use App\Filament\Resources\PollinationResource;
use Rupadana\ApiService\ApiService;

class PollinationApiService extends ApiService
{
    protected static ?string $resource = PollinationResource::class;

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
