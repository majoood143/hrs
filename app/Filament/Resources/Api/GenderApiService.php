<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Gender\Handlers;
use App\Filament\Resources\GenderResource;
use Rupadana\ApiService\ApiService;

class GenderApiService extends ApiService
{
    protected static ?string $resource = GenderResource::class;

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
