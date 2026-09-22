<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Vaccination\Handlers;
use App\Filament\Resources\VaccinationResource;
use Rupadana\ApiService\ApiService;

class VaccinationApiService extends ApiService
{
    protected static ?string $resource = VaccinationResource::class;

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
