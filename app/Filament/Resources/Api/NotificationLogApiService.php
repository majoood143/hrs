<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\NotificationLog\Handlers;
use App\Filament\Resources\NotificationLogResource;
use Rupadana\ApiService\ApiService;

class NotificationLogApiService extends ApiService
{
    protected static ?string $resource = NotificationLogResource::class;

    public static function handlers(): array
    {
        return [
            Handlers\PaginationHandler::class,
            Handlers\DetailHandler::class,
        ];
    }
}
