<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Center\Handlers;
use App\Filament\Resources\CenterResource;
use Rupadana\ApiService\ApiService;

class CenterApiService extends ApiService
{
    protected static ?string $resource = CenterResource::class;

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
