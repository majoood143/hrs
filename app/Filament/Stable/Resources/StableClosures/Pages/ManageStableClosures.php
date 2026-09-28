<?php

namespace App\Filament\Stable\Resources\StableClosures\Pages;

use App\Filament\Stable\Resources\StableClosures\StableClosureResource;
use App\Models\StableClosure;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStableClosures extends ManageRecords
{
    protected static string $resource = StableClosureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus')
                ->after(fn (StableClosure $record) => StableClosureResource::apply($record)),
        ];
    }
}
