<?php

namespace App\Filament\Resources\HorseSalePostResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\EditAction;
use App\Filament\Resources\HorseSalePostResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewHorseSalePost extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = HorseSalePostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
