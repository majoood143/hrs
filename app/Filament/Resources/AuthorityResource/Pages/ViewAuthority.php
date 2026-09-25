<?php

namespace App\Filament\Resources\AuthorityResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\EditAction;
use App\Filament\Resources\AuthorityResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAuthority extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = AuthorityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
