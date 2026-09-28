<?php

namespace App\Filament\Stable\Resources\StableSchedules\Pages;

use App\Filament\Stable\Resources\StableSchedules\StableScheduleResource;
use App\Models\StableSchedule;
use App\Services\Stables\SlotGenerator;
use Filament\Resources\Pages\CreateRecord;

class CreateStableSchedule extends CreateRecord
{
    protected static string $resource = StableScheduleResource::class;

    protected function afterCreate(): void
    {
        /** @var StableSchedule $schedule */
        $schedule = $this->record;

        StableScheduleResource::notifySync(app(SlotGenerator::class)->sync($schedule, refresh: true));
    }

    protected function getCreatedNotification(): null
    {
        return null;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
