<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\TransferPost\Handlers;
use App\Filament\Resources\TransferPostResource;
use Rupadana\ApiService\ApiService;

class TransferPostApiService extends ApiService
{
    protected static ?string $resource = TransferPostResource::class;

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
