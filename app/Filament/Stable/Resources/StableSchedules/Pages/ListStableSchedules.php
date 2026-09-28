<?php

namespace App\Filament\Stable\Resources\StableSchedules\Pages;

use App\Filament\Stable\Resources\StableSchedules\StableScheduleResource;
use App\Models\Stable;
use App\Services\Stables\SlotGenerator;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListStableSchedules extends ListRecords
{
    protected static string $resource = StableScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label(__('stable_panel.schedules.generate_now'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    /** @var Stable $stable */
                    $stable = Filament::getTenant();
                    StableScheduleResource::notifySync(app(SlotGenerator::class)->syncStable($stable));
                }),
            CreateAction::make()->icon('heroicon-o-plus'),
        ];
    }
}
