<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Attachement\Handlers;
use App\Filament\Resources\AttachementResource;
use Rupadana\ApiService\ApiService;

class AttachementApiService extends ApiService
{
    protected static ?string $resource = AttachementResource::class;

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
