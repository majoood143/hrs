<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Authority\Handlers;
use App\Filament\Resources\AuthorityResource;
use Rupadana\ApiService\ApiService;

class AuthorityApiService extends ApiService
{
    protected static ?string $resource = AuthorityResource::class;

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
