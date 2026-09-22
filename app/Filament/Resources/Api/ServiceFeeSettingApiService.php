<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\ServiceFeeSetting\Handlers;
use App\Filament\Resources\ServiceFeeSettingResource;
use Rupadana\ApiService\ApiService;

class ServiceFeeSettingApiService extends ApiService
{
    protected static ?string $resource = ServiceFeeSettingResource::class;

    public static function handlers(): array
    {
        return [
            Handlers\PaginationHandler::class,
            Handlers\DetailHandler::class,
        ];
    }
}
