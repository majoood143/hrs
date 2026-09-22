<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\CenterService\Handlers;
use App\Filament\Resources\CenterServiceResource;
use Rupadana\ApiService\ApiService;

class CenterServiceApiService extends ApiService
{
    protected static ?string $resource = CenterServiceResource::class;

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
