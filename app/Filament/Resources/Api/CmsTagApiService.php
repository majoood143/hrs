<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\CmsTag\Handlers;
use App\Filament\Resources\CmsTagResource;
use Rupadana\ApiService\ApiService;

class CmsTagApiService extends ApiService
{
    protected static ?string $resource = CmsTagResource::class;

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
