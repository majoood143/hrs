<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Farrier\Handlers;
use App\Filament\Resources\FarrierResource;
use Rupadana\ApiService\ApiService;

class FarrierApiService extends ApiService
{
    protected static ?string $resource = FarrierResource::class;

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
