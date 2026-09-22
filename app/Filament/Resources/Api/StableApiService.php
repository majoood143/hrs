<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Stable\Handlers;
use App\Filament\Resources\StableResource;
use Rupadana\ApiService\ApiService;

class StableApiService extends ApiService
{
    protected static ?string $resource = StableResource::class;

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
