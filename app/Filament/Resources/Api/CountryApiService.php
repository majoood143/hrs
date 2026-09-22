<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Country\Handlers;
use App\Filament\Resources\CountryResource;
use Rupadana\ApiService\ApiService;

class CountryApiService extends ApiService
{
    protected static ?string $resource = CountryResource::class;

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
