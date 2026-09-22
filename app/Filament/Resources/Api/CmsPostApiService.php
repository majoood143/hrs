<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\CmsPost\Handlers;
use App\Filament\Resources\CmsPostResource;
use Rupadana\ApiService\ApiService;

class CmsPostApiService extends ApiService
{
    protected static ?string $resource = CmsPostResource::class;

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
