<?php

namespace App\Filament\Resources\StableResource\Pages;

use App\Filament\Resources\StableResource;
use App\Filament\Support\InUseDeleteActions;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditStable extends EditRecord
{
    protected static string $resource = StableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            InUseDeleteActions::delete('admin_stable'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
