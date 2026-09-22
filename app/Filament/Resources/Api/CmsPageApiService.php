<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\CmsPage\Handlers;
use App\Filament\Resources\CmsPageResource;
use Rupadana\ApiService\ApiService;

class CmsPageApiService extends ApiService
{
    protected static ?string $resource = CmsPageResource::class;

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
