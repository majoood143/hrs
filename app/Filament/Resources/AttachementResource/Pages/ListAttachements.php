<?php

namespace App\Filament\Resources\AttachementResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\AttachementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttachements extends ListRecords
{
    use HasExportActions;

    protected static string $resource = AttachementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
