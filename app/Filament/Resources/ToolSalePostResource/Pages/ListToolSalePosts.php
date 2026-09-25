<?php

namespace App\Filament\Resources\ToolSalePostResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\ToolSalePostResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListToolSalePosts extends ListRecords
{
    use HasExportActions;

    protected static string $resource = ToolSalePostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
