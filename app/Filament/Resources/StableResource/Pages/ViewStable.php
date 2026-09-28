<?php

namespace App\Filament\Resources\StableResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\StableResource;
use App\Filament\Resources\StableResource\StableApprovalActions;
use App\Filament\Widgets\Stables\StableChangeLogWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStable extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = StableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            StableApprovalActions::openPanel(),
            ...StableApprovalActions::all(),
            EditAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [StableChangeLogWidget::class];
    }
}
