<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\CmsMenu\Handlers;
use App\Filament\Resources\CmsMenuResource;
use Rupadana\ApiService\ApiService;

class CmsMenuApiService extends ApiService
{
    protected static ?string $resource = CmsMenuResource::class;

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
