<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\CommissionSetting\Handlers;
use App\Filament\Resources\CommissionSettingResource;
use Rupadana\ApiService\ApiService;

class CommissionSettingApiService extends ApiService
{
    protected static ?string $resource = CommissionSettingResource::class;

    public static function handlers(): array
    {
        return [
            Handlers\PaginationHandler::class,
            Handlers\DetailHandler::class,
        ];
    }
}
