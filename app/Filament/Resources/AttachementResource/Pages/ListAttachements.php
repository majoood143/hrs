<?php

namespace App\Filament\Resources\AttachementResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\AttachementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttachements extends ListRecords
{
    protected static string $resource = AttachementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
