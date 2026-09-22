<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Partner\Handlers;
use App\Filament\Resources\PartnerResource;
use Rupadana\ApiService\ApiService;

class PartnerApiService extends ApiService
{
    protected static ?string $resource = PartnerResource::class;

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
