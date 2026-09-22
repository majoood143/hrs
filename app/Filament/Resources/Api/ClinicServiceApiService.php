<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\ClinicService\Handlers;
use App\Filament\Resources\ClinicServiceResource;
use Rupadana\ApiService\ApiService;

class ClinicServiceApiService extends ApiService
{
    protected static ?string $resource = ClinicServiceResource::class;

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
