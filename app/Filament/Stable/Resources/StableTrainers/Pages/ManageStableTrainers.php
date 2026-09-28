<?php

namespace App\Filament\Stable\Resources\StableTrainers\Pages;

use App\Filament\Stable\Resources\StableTrainers\StableTrainerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStableTrainers extends ManageRecords
{
    protected static string $resource = StableTrainerResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->icon('heroicon-o-plus')];
    }
}
