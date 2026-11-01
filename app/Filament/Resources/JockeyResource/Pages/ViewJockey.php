<?php

namespace App\Filament\Resources\JockeyResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\JockeyResource;
use App\Filament\Support\PendingPosts;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewJockey extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = JockeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PendingPosts::approveAction(),
            EditAction::make(),
        ];
    }
}
