<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\ShopService\Handlers;
use App\Filament\Resources\ShopServiceResource;
use Rupadana\ApiService\ApiService;

class ShopServiceApiService extends ApiService
{
    protected static ?string $resource = ShopServiceResource::class;

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
