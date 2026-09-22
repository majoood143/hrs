<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Event\Handlers;
use App\Filament\Resources\EventResource;
use Rupadana\ApiService\ApiService;

class EventApiService extends ApiService
{
    protected static ?string $resource = EventResource::class;

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
