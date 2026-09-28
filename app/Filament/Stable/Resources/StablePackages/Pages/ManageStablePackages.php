<?php

namespace App\Filament\Stable\Resources\StablePackages\Pages;

use App\Filament\Stable\Resources\StablePackages\StablePackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStablePackages extends ManageRecords
{
    protected static string $resource = StablePackageResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->icon('heroicon-o-plus')];
    }
}
