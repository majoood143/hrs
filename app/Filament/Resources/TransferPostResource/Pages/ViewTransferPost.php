<?php

namespace App\Filament\Resources\TransferPostResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\TransferPostResource;
use App\Filament\Support\PendingPosts;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTransferPost extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = TransferPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PendingPosts::approveAction(),
            EditAction::make(),
        ];
    }
}
