<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\StableService\Handlers;
use App\Filament\Resources\StableServiceResource;
use Rupadana\ApiService\ApiService;

class StableServiceApiService extends ApiService
{
    protected static ?string $resource = StableServiceResource::class;

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
