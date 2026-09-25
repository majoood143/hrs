<?php

namespace App\Filament\Resources\TransferPostResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\EditAction;
use App\Filament\Resources\TransferPostResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTransferPost extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = TransferPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
