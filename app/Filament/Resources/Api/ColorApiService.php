<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Color\Handlers;
use App\Filament\Resources\ColorResource;
use Rupadana\ApiService\ApiService;

class ColorApiService extends ApiService
{
    protected static ?string $resource = ColorResource::class;

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
