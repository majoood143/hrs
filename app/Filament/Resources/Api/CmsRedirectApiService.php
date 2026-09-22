<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\CmsRedirect\Handlers;
use App\Filament\Resources\CmsRedirectResource;
use Rupadana\ApiService\ApiService;

class CmsRedirectApiService extends ApiService
{
    protected static ?string $resource = CmsRedirectResource::class;

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
