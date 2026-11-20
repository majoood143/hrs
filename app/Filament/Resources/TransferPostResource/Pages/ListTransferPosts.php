<?php

namespace App\Filament\Resources\TransferPostResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\TransferPostResource;
use App\Filament\Support\ListingViews\ListingViewWidgets;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTransferPosts extends ListRecords
{
    use HasExportActions;

    protected static string $resource = TransferPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return ListingViewWidgets::forList(static::$resource);
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 3;
    }
}
