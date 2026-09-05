<?php

namespace App\Filament\Resources\AttachementResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Resources\AttachementResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAttachement extends ViewRecord
{
    protected static string $resource = AttachementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
