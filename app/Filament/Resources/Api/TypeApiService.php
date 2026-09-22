<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Type\Handlers;
use App\Filament\Resources\TypeResource;
use Rupadana\ApiService\ApiService;

class TypeApiService extends ApiService
{
    protected static ?string $resource = TypeResource::class;

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
