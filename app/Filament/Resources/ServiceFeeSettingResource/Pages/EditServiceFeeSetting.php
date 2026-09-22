<?php

namespace App\Filament\Resources\ServiceFeeSettingResource\Pages;

use App\Filament\Resources\ServiceFeeSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditServiceFeeSetting extends EditRecord
{
    protected static string $resource = ServiceFeeSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
