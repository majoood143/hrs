<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\ToolSalePost\Handlers;
use App\Filament\Resources\ToolSalePostResource;
use Rupadana\ApiService\ApiService;

class ToolSalePostApiService extends ApiService
{
    protected static ?string $resource = ToolSalePostResource::class;

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
