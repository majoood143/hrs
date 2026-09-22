<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\ServiceOrder\Handlers;
use App\Filament\Resources\ServiceOrderResource;
use Rupadana\ApiService\ApiService;

class ServiceOrderApiService extends ApiService
{
    protected static ?string $resource = ServiceOrderResource::class;

    public static function handlers(): array
    {
        return [
            Handlers\PaginationHandler::class,
            Handlers\DetailHandler::class,
        ];
    }
}
