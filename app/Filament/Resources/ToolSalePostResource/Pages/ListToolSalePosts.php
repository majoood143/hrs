<?php

namespace App\Filament\Resources\ToolSalePostResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ToolSalePostResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListToolSalePosts extends ListRecords
{
    protected static string $resource = ToolSalePostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
