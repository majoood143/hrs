<?php

namespace App\Filament\Resources\GameAttemptResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\GameAttemptResource;
use Filament\Resources\Pages\ListRecords;

class ListGameAttempts extends ListRecords
{
    use HasExportActions;

    protected static string $resource = GameAttemptResource::class;
}
