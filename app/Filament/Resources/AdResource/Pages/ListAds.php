<?php

namespace App\Filament\Resources\AdResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\AdResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAds extends ListRecords
{
    use HasExportActions;

    protected static string $resource = AdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
