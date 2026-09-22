<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\City\Handlers;
use App\Filament\Resources\CityResource;
use Rupadana\ApiService\ApiService;

class CityApiService extends ApiService
{
    protected static ?string $resource = CityResource::class;

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
