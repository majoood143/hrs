<?php

namespace App\Filament\Stable\Resources\StableOfferings\Pages;

use App\Filament\Stable\Resources\StableOfferings\StableOfferingResource;
use App\Models\StableOffering;
use App\Services\Stables\SlotGenerator;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStableOffering extends EditRecord
{
    protected static string $resource = StableOfferingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** A new duration or capacity reaches the slots that have no bookings yet; booked ones stay as sold. */
    protected function afterSave(): void
    {
        /** @var StableOffering $offering */
        $offering = $this->record;
        $generator = app(SlotGenerator::class);

        foreach ($offering->schedules()->with(['stable', 'offering'])->get() as $schedule) {
            $generator->sync($schedule, refresh: true);
        }
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }
}
