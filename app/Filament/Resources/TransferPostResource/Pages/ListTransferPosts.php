<?php

namespace App\Filament\Resources\TransferPostResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\TransferPostResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransferPosts extends ListRecords
{
    protected static string $resource = TransferPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
