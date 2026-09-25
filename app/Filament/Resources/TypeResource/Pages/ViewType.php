<?php

namespace App\Filament\Resources\TypeResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\EditAction;
use App\Filament\Resources\TypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewType extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = TypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
