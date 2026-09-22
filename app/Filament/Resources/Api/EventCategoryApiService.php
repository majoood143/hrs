<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\EventCategory\Handlers;
use App\Filament\Resources\EventCategoryResource;
use Rupadana\ApiService\ApiService;

class EventCategoryApiService extends ApiService
{
    protected static ?string $resource = EventCategoryResource::class;

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
