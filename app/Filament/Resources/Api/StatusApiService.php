<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Status\Handlers;
use App\Filament\Resources\StatusResource;
use Rupadana\ApiService\ApiService;

class StatusApiService extends ApiService
{
    protected static ?string $resource = StatusResource::class;

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
