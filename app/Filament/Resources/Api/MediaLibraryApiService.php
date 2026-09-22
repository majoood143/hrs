<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\MediaLibrary\Handlers;
use App\Filament\Resources\MediaLibraryResource;
use Rupadana\ApiService\ApiService;

class MediaLibraryApiService extends ApiService
{
    protected static ?string $resource = MediaLibraryResource::class;

    public static function handlers(): array
    {
        return [
            Handlers\PaginationHandler::class,
            Handlers\DetailHandler::class,
        ];
    }
}
