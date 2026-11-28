<?php

namespace App\Filament\Stable\Resources\StableExtras\Pages;

use App\Filament\Stable\Resources\StableExtras\StableExtraResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStableExtras extends ManageRecords
{
    protected static string $resource = StableExtraResource::class;

    public function getSubheading(): ?string
    {
        return StableExtraResource::stableOf($this)?->extras_enabled === false ? __('stable_extras.panel.off_notice') : null;
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->icon('heroicon-o-plus')];
    }
}
