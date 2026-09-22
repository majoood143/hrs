<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Clinic\Handlers;
use App\Filament\Resources\ClinicResource;
use Rupadana\ApiService\ApiService;

class ClinicApiService extends ApiService
{
    protected static ?string $resource = ClinicResource::class;

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
