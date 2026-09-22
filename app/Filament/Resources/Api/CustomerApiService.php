<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Customer\Handlers;
use App\Filament\Resources\CustomerResource;
use Rupadana\ApiService\ApiService;

class CustomerApiService extends ApiService
{
    protected static ?string $resource = CustomerResource::class;

    public static function handlers(): array
    {
        return [
            Handlers\PaginationHandler::class,
            Handlers\DetailHandler::class,
        ];
    }
}
