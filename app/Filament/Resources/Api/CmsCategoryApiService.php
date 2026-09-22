<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\CmsCategory\Handlers;
use App\Filament\Resources\CmsCategoryResource;
use Rupadana\ApiService\ApiService;

class CmsCategoryApiService extends ApiService
{
    protected static ?string $resource = CmsCategoryResource::class;

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
