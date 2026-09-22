<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Region\Handlers;
use App\Filament\Resources\RegionResource;
use Rupadana\ApiService\ApiService;

class RegionApiService extends ApiService
{
    protected static ?string $resource = RegionResource::class;

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
