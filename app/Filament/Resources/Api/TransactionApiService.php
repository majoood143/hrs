<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Transaction\Handlers;
use App\Filament\Resources\TransactionResource;
use Rupadana\ApiService\ApiService;

class TransactionApiService extends ApiService
{
    protected static ?string $resource = TransactionResource::class;

    public static function handlers(): array
    {
        return [
            Handlers\PaginationHandler::class,
            Handlers\DetailHandler::class,
        ];
    }
}
