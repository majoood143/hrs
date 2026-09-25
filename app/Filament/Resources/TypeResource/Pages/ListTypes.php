<?php

namespace App\Filament\Resources\TypeResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\TypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTypes extends ListRecords
{
    use HasExportActions;

    protected static string $resource = TypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
