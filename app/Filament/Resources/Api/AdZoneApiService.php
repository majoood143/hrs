<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\AdZoneResource;
use App\Filament\Resources\Api\AdZone\Handlers;
use Rupadana\ApiService\ApiService;

class AdZoneApiService extends ApiService
{
    protected static ?string $resource = AdZoneResource::class;

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
