<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use App\Filament\Support\InUseDeleteActions;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            InUseDeleteActions::delete('admin_service'),
        ];
    }

    protected function getRedirectUrl(): string
    { // Redirect to the list page after creation
        return $this->getResource()::getUrl('index');
    }
}
