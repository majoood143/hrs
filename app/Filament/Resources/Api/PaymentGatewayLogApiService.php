<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\PaymentGatewayLog\Handlers;
use App\Filament\Resources\PaymentGatewayLogResource;
use Rupadana\ApiService\ApiService;

class PaymentGatewayLogApiService extends ApiService
{
    protected static ?string $resource = PaymentGatewayLogResource::class;

    public static function handlers(): array
    {
        return [
            Handlers\PaginationHandler::class,
            Handlers\DetailHandler::class,
        ];
    }
}
