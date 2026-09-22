<?php

namespace App\Filament\Resources\ServiceFeeSettingResource\Pages;

use App\Filament\Resources\ServiceFeeSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceFeeSettings extends ListRecords
{
    protected static string $resource = ServiceFeeSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
