<?php

namespace App\Filament\Resources\MobileAnnouncementResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\MobileAnnouncementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMobileAnnouncements extends ListRecords
{
    use HasExportActions;

    protected static string $resource = MobileAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
