<?php

namespace App\Filament\Resources\ToolSalePostResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\EditAction;
use App\Filament\Resources\ToolSalePostResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewToolSalePost extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = ToolSalePostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
