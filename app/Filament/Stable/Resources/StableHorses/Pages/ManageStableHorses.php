<?php

namespace App\Filament\Stable\Resources\StableHorses\Pages;

use App\Filament\Stable\Resources\StableHorses\StableHorseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStableHorses extends ManageRecords
{
    protected static string $resource = StableHorseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->icon('heroicon-o-plus')];
    }
}
