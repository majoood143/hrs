<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Shop\Handlers;
use App\Filament\Resources\ShopResource;
use Rupadana\ApiService\ApiService;

class ShopApiService extends ApiService
{
    protected static ?string $resource = ShopResource::class;

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
