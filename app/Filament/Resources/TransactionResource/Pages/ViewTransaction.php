<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\EditAction;
use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
