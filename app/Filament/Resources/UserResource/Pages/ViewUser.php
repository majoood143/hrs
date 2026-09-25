<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\EditAction;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
