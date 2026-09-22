<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\AdResource;
use App\Filament\Resources\Api\Ad\Handlers;
use Rupadana\ApiService\ApiService;

class AdApiService extends ApiService
{
    protected static ?string $resource = AdResource::class;

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
