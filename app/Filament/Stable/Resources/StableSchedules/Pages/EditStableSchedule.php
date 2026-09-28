<?php

namespace App\Filament\Stable\Resources\StableSchedules\Pages;

use App\Filament\Stable\Resources\StableSchedules\StableScheduleResource;
use App\Models\StableSchedule;
use App\Services\Stables\SlotGenerator;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStableSchedule extends EditRecord
{
    protected static string $resource = StableScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->modalDescription(__('stable_panel.schedules.delete_hint')),
        ];
    }

    protected function afterSave(): void
    {
        /** @var StableSchedule $schedule */
        $schedule = $this->record;

        StableScheduleResource::notifySync(app(SlotGenerator::class)->sync($schedule->refresh(), refresh: true));
    }

    protected function getSavedNotification(): null
    {
        return null;
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }
}
