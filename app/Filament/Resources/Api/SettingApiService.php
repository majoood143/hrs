<?php

namespace App\Filament\Resources\Api;

use App\Filament\Resources\Api\Setting\Handlers;
use App\Filament\Resources\SettingResource;
use Rupadana\ApiService\ApiService;

class SettingApiService extends ApiService
{
    protected static ?string $resource = SettingResource::class;

    public static function handlers(): array
    {
        return [
            Handlers\PaginationHandler::class,
            Handlers\DetailHandler::class,
        ];
    }
}
