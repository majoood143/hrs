<?php

namespace App\Filament\Resources\GameAttemptResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\GameAttemptResource;
use App\Filament\Resources\GameAttemptResource\AttemptActions;
use Filament\Resources\Pages\ViewRecord;

class ViewGameAttempt extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = GameAttemptResource::class;

    protected function getHeaderActions(): array
    {
        return AttemptActions::all();
    }
}
