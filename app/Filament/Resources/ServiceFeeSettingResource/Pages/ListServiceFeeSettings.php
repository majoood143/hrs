<?php

namespace App\Filament\Resources\ServiceFeeSettingResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\ServiceFeeSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceFeeSettings extends ListRecords
{
    use HasExportActions;

    protected static string $resource = ServiceFeeSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
