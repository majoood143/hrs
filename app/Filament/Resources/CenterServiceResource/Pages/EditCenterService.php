<?php

namespace App\Filament\Resources\CenterServiceResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\CenterServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCenterService extends EditRecord
{
    protected static string $resource = CenterServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
