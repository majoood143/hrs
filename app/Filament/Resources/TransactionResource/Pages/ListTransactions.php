<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransactions extends ListRecords
{
    use HasExportActions;

    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
