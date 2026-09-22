<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\HorseSalePost\Handlers;
use App\Filament\Resources\HorseSalePostResource;
use Rupadana\ApiService\ApiService;

class HorseSalePostApiService extends ApiService
{
    protected static ?string $resource = HorseSalePostResource::class;

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
