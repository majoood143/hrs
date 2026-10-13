<?php

namespace App\Filament\Resources\ToolSalePostResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\ToolSalePostResource;
use App\Filament\Support\PendingPosts;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewToolSalePost extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = ToolSalePostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PendingPosts::approveAction(),
            EditAction::make(),
        ];
    }
}
