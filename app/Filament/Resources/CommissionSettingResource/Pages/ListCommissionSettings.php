<?php

namespace App\Filament\Resources\CommissionSettingResource\Pages;

use App\Filament\Resources\CommissionSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCommissionSettings extends ListRecords
{
    protected static string $resource = CommissionSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
